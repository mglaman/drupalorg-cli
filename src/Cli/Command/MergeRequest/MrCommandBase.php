<?php

namespace mglaman\DrupalOrgCli\Command\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\ListMergeRequestsAction;
use mglaman\DrupalOrg\Enum\MergeRequestState;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\MergeRequestRef;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestItem;
use mglaman\DrupalOrgCli\Command\Command;
use mglaman\DrupalOrgCli\Command\Issue\IssueCommandBase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

abstract class MrCommandBase extends IssueCommandBase
{
    protected bool $requiresRepository = false;

    protected int $mrIid;

    protected ?MergeRequestRef $mrRef = null;

    protected function configureNidAndMrIid(): void
    {
        $this
            ->addArgument('nid', InputArgument::OPTIONAL, 'The issue NID, project-path!iid (quote in zsh), or GitLab MR URL')
            ->addArgument('mr-iid', InputArgument::OPTIONAL, 'The merge request IID');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $nidArg = (string) $input->getArgument('nid');
        $ref = $nidArg !== '' ? MergeRequestRef::tryParse($nidArg) : null;

        if ($ref !== null) {
            // Skip IssueCommandBase NID resolution — go straight to Command::initialize.
            Command::initialize($input, $output);
            $this->mrRef = $ref;
            $this->nid = '';

            if ($ref->mrIid !== null) {
                $this->mrIid = $ref->mrIid;
            } else {
                $mrIidArg = $input->getArgument('mr-iid');
                if ($mrIidArg === null || $mrIidArg === '') {
                    throw new \RuntimeException('Argument mr-iid is required when project-path has no !iid.');
                }
                $this->mrIid = (int) $mrIidArg;
            }
            return;
        }

        parent::initialize($input, $output);

        $mrIid = $this->stdIn->getArgument('mr-iid');
        if ($mrIid !== null && $mrIid !== '') {
            $this->mrIid = (int) $mrIid;
            return;
        }

        // mr-iid not provided — auto-select from open merge requests.
        $listAction = new ListMergeRequestsAction($this->client, new GitLabClient());
        $listResult = $listAction($this->nid, MergeRequestState::Opened);
        $mergeRequests = $listResult->mergeRequests;

        if ($mergeRequests === []) {
            throw new \RuntimeException('No open merge requests found for this issue.');
        }

        if (count($mergeRequests) === 1) {
            $this->mrIid = $mergeRequests[0]->iid;
            $this->stdOut->writeln(sprintf(
                '<info>Auto-selected MR !%d: %s</info>',
                $mergeRequests[0]->iid,
                $mergeRequests[0]->title
            ));
            return;
        }

        // Multiple open MRs.
        if (!self::$interactive) {
            $this->stdErr->writeln('<error>Multiple open merge requests found. Specify one of:</error>');
            foreach ($mergeRequests as $mr) {
                $this->stdErr->writeln(sprintf('  !%d — %s', $mr->iid, $mr->title));
            }
            throw new \RuntimeException('Argument mr-iid is required when multiple merge requests are open.');
        }

        $choices = array_map(
            static fn(MergeRequestItem $mr) => sprintf('!%d — %s', $mr->iid, $mr->title),
            $mergeRequests
        );
        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion('Select a merge request:', $choices);
        $selected = (string) $helper->ask($input, $output, $question);
        if (!preg_match('/^!(\d+)/', $selected, $matches)) {
            throw new \RuntimeException('Failed to extract merge request IID from selection.');
        }
        $this->mrIid = (int) $matches[1];
    }
}
