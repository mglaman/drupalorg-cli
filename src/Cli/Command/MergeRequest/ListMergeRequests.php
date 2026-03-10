<?php

namespace mglaman\DrupalOrgCli\Command\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\ListMergeRequestsAction;
use mglaman\DrupalOrg\Enum\MergeRequestState;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\MergeRequestRef;
use mglaman\DrupalOrgCli\Command\Command;
use mglaman\DrupalOrgCli\Command\Issue\IssueCommandBase;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListMergeRequests extends IssueCommandBase
{
    protected bool $requiresRepository = false;

    protected ?MergeRequestRef $mrRef = null;

    protected function configure(): void
    {
        $this
            ->setName('mr:list')
            ->setAliases(['mrl'])
            ->addArgument('nid', InputArgument::OPTIONAL, 'The issue NID, project-path (or project-path!iid; quote in zsh), or GitLab URL')
            ->addOption(
                'state',
                null,
                InputOption::VALUE_OPTIONAL,
                'Filter by state: opened, closed, merged, all. Defaults to opened.',
                'opened'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format: text, json, md, llm. Defaults to text.',
                'text'
            )
            ->setDescription('List merge requests for a Drupal.org issue fork or project.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $nidArg = (string) $input->getArgument('nid');
        $ref = $nidArg !== '' ? MergeRequestRef::tryParse($nidArg) : null;

        if ($ref !== null) {
            Command::initialize($input, $output);
            $this->mrRef = $ref;
            $this->nid = '';
            return;
        }

        parent::initialize($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $state = MergeRequestState::from((string) ($this->stdIn->getOption('state') ?? 'opened'));
        $format = (string) ($this->stdIn->getOption('format') ?? 'text');

        $action = new ListMergeRequestsAction($this->client, new GitLabClient());
        $result = $action($this->nid ?? '', $state, $this->mrRef);

        if ($this->writeFormatted($result, $format)) {
            return 0;
        }

        if ($result->mergeRequests === []) {
            $this->stdOut->writeln(sprintf('No %s merge requests found.', $state->value));
            return 0;
        }

        $table = new Table($this->stdOut);
        $table->setHeaders(['IID', 'Title', 'Source Branch', 'State', 'Mergeable', 'Author', 'Updated']);
        foreach ($result->mergeRequests as $mr) {
            $table->addRow([
                $mr->iid,
                $mr->title,
                $mr->sourceBranch,
                $mr->state,
                $mr->isMergeable ? 'yes' : 'no',
                $mr->author,
                $mr->updatedAt,
            ]);
        }
        $table->render();

        return 0;
    }
}
