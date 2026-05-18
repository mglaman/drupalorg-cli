<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\GitLab\GetGitLabIssueAction;
use mglaman\DrupalOrg\Action\Issue\GetIssueAction;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrg\IssueTrait;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Show extends Command
{
    use IssueTrait;

    protected function configure(): void
    {
        $this
            ->setName('issue:show')
            ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID or a GitLab work item URL')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output options: text, json, md, llm. Defaults to text.',
                'text'
            )
            ->addOption('with-comments', null, InputOption::VALUE_NONE, 'Also fetch issue comments.')
            ->setDescription('Show a given issue information.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nid = $this->stdIn->getArgument('nid');
        $format = $this->stdIn->getOption('format');

        $ref = WorkItemRef::tryParse((string) $nid);
        if ($ref !== null) {
            $result = (new GetGitLabIssueAction())($ref);
            if ($this->writeFormatted($result, (string) $format)) {
                return 0;
            }
            $issue = $result->issue;
            $this->stdOut->writeln(sprintf('Title: %s', $issue->title));
            $this->stdOut->writeln(sprintf('State: %s', $issue->state));
            $this->stdOut->writeln(sprintf('Author: %s', $issue->author));
            if ($issue->assignees !== []) {
                $this->stdOut->writeln(sprintf('Assignees: %s', implode(', ', $issue->assignees)));
            }
            if ($issue->labels !== []) {
                $this->stdOut->writeln(sprintf('Labels: %s', implode(', ', $issue->labels)));
            }
            $this->stdOut->writeln(sprintf('Created: %s', $issue->createdAt));
            $this->stdOut->writeln(sprintf('Updated: %s', $issue->updatedAt));
            $this->stdOut->writeln(sprintf('URL: %s', $issue->webUrl));
            $this->stdOut->writeln(sprintf("\nDescription:\n%s", $issue->description));
            return 0;
        }

        $withComments = (bool) $this->stdIn->getOption('with-comments');
        $result = (new GetIssueAction($this->client))($nid, $withComments);

        if ($this->writeFormatted($result, (string) $format)) {
            return 0;
        }
        $this->stdOut->writeln(sprintf('Title: %s', $result->title));
        $this->stdOut->writeln(sprintf('Status: %s', $this->getIssueStatusLabel($result->fieldIssueStatus)));
        $this->stdOut->writeln(sprintf('Project: %s', $result->fieldProjectMachineName));
        $this->stdOut->writeln(sprintf('Version: %s', $result->fieldIssueVersion));
        $this->stdOut->writeln(sprintf('Component: %s', $result->fieldIssueComponent));
        $this->stdOut->writeln(sprintf('Priority: %s', $this->getIssuePriorityLabel($result->fieldIssuePriority)));
        $this->stdOut->writeln(sprintf('Category: %s', $this->getIssueCategoryLabel($result->fieldIssueCategory)));
        $this->stdOut->writeln(sprintf('Reporter: %s', $result->authorId ?? ''));
        $this->stdOut->writeln(sprintf('Created: %s', date('r', $result->created)));
        $this->stdOut->writeln(sprintf('Updated: %s', date('r', $result->changed)));
        $this->stdOut->writeln(sprintf("\nIssue summary:\n%s", strip_tags($result->bodyValue ?? '')));
        if ($result->comments !== []) {
            $this->stdOut->writeln('');
            foreach ($result->comments as $index => $comment) {
                $this->stdOut->writeln(sprintf(
                    "Comment #%d by %s  (%s)",
                    $index + 1,
                    $comment->authorName,
                    date('r', $comment->created)
                ));
                $this->stdOut->writeln(strip_tags($comment->bodyValue ?? ''));
                $this->stdOut->writeln('');
            }
        }
        return 0;
    }
}
