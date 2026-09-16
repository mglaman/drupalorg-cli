<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\GitLab\GetGitLabIssueAction;
use mglaman\DrupalOrg\Action\Issue\GetIssueAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrg\IssueTrait;
use mglaman\DrupalOrg\MigratedIssueException;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
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
            ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID, project#nid, or GitLab work item URL. A migrated issue is followed to its work item.')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output options: text, json, md, llm. Defaults to text.',
                'text'
            )
            ->addOption('with-comments', null, InputOption::VALUE_NONE, 'Also fetch issue comments. System-generated messages are skipped.')
            ->addOption('include-bot-comments', null, InputOption::VALUE_NONE, 'Keep drupalbot replies when fetching GitLab work item comments. Off by default because the work item fields already reflect label and assignee changes.')
            ->setDescription('Show a given issue information.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nid = (string) $this->stdIn->getArgument('nid');
        $format = (string) $this->stdIn->getOption('format');
        $withComments = (bool) $this->stdIn->getOption('with-comments');

        $ref = WorkItemRef::tryParse($nid);
        if ($ref === null) {
            try {
                return $this->showIssue((new GetIssueAction($this->client))($nid, $withComments), $format);
            } catch (MigratedIssueException $e) {
                $ref = $e->ref;
            }
        }
        return $this->showWorkItem($ref, $withComments, $format);
    }

    private function showWorkItem(WorkItemRef $ref, bool $withComments, string $format): int
    {
        $includeBotComments = (bool) $this->stdIn->getOption('include-bot-comments');
        $result = (new GetGitLabIssueAction(new GitLabClient()))($ref, $withComments, $includeBotComments);
        if ($this->writeFormatted($result, $format)) {
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
        foreach ($result->comments as $index => $comment) {
            $this->stdOut->writeln(sprintf(
                "\nComment #%d by %s (%s):\n%s",
                $index + 1,
                $comment->author,
                $comment->createdAt,
                $comment->body
            ));
        }
        return 0;
    }

    private function showIssue(IssueResult $result, string $format): int
    {
        if ($this->writeFormatted($result, $format)) {
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
