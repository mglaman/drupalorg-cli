<?php

namespace mglaman\DrupalOrgCli\Command\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestResult;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Show extends MrCommandBase
{
    protected function configure(): void
    {
        $this
            ->setName('mr:show')
            ->setDescription('Show merge request state, the work items it closes, and optionally its review notes.')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format: text, json, md, llm. Defaults to text.',
                'text'
            )
            ->addOption('with-comments', null, InputOption::VALUE_NONE, 'Also fetch merge request notes. System notes are skipped. Implied when the MR URL ends in #note_<id>, and that note is marked.')
            ->addOption('include-bot-comments', null, InputOption::VALUE_NONE, 'Keep drupalbot notes when fetching comments.');
        $this->configureNidAndMrIid();
    }

    protected function createGitLabClient(): GitLabClient
    {
        return new GitLabClient();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = (string) ($this->stdIn->getOption('format') ?? 'text');

        $action = new GetMergeRequestAction($this->client, $this->createGitLabClient());
        $result = $action(
            $this->nid ?? '',
            $this->mrIid,
            $this->mrRef,
            (bool) $this->stdIn->getOption('with-comments'),
            (bool) $this->stdIn->getOption('include-bot-comments'),
        );

        if ($this->writeFormatted($result, $format)) {
            return 0;
        }

        $this->writeText($result);
        return 0;
    }

    private function writeText(MergeRequestResult $result): void
    {
        $this->stdOut->writeln(sprintf('MR !%d: %s', $result->iid, $result->title));
        $this->stdOut->writeln(sprintf('Author: %s', $result->author));
        $this->stdOut->writeln(sprintf('State: %s%s', $result->state, $result->draft ? ' (draft)' : ''));
        $this->stdOut->writeln(sprintf('Branch: %s -> %s', $result->sourceBranch, $result->targetBranch));
        $this->stdOut->writeln(sprintf('Head SHA: %s', $result->headSha));
        $this->stdOut->writeln(sprintf('Merge status: %s', $result->detailedMergeStatus));
        $this->stdOut->writeln(sprintf('Has conflicts: %s', $result->hasConflicts ? 'yes' : 'no'));
        $this->stdOut->writeln(sprintf('Blocking discussions resolved: %s', $result->blockingDiscussionsResolved ? 'yes' : 'no'));
        $this->stdOut->writeln(trim(sprintf('Pipeline: %s %s', $result->pipelineStatus ?? 'none', $result->pipelineUrl ?? '')));
        $this->stdOut->writeln(sprintf('URL: %s', $result->webUrl));

        $this->stdOut->writeln("\nCloses issues:");
        if ($result->closesIssues === []) {
            $this->stdOut->writeln('  none');
        }
        foreach ($result->closesIssues as $issue) {
            $this->stdOut->writeln(sprintf('  #%d [%s] %s', $issue->iid, $issue->state, $issue->title));
            if ($issue->labels !== []) {
                $this->stdOut->writeln(sprintf('    Labels: %s', implode(', ', $issue->labels)));
            }
        }

        foreach ($result->comments as $comment) {
            $this->stdOut->writeln(sprintf(
                "\nNote %d by %s (%s)%s:",
                $comment->id,
                $comment->author,
                $comment->createdAt,
                $result->isTargetNote($comment) ? ' [linked note]' : ''
            ));
            if ($comment->path !== null) {
                $this->stdOut->writeln(sprintf('On %s:%s', $comment->path, $comment->line ?? ''));
            }
            $this->stdOut->writeln($comment->body);
        }
    }
}
