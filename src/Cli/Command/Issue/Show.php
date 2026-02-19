<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use mglaman\DrupalOrg\IssueTrait;

class Show extends IssueCommandBase
{
    use IssueTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('issue:show')
            ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output options: text, json. Defaults to text.',
                'text'
            )
            ->setDescription('Show a given issue information.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nid = $this->stdIn->getArgument('nid');
        $issue = $this->client->getNode($nid);
        $format = $this->stdIn->getOption('format');

        if ($format == 'json') {
            $this->stdOut->writeln(json_encode($issue));
            return 0;
        }
        // format option is text.
        $this->stdOut->writeln(sprintf('Title: %s', $issue->title));
        $this->stdOut->writeln(sprintf('Status: %s', $this->getIssueStatusLabel($issue->fieldIssueStatus)));
        $this->stdOut->writeln(sprintf('Project: %s', $issue->fieldProjectMachineName));
        $this->stdOut->writeln(sprintf('Version: %s', $issue->fieldIssueVersion));
        $this->stdOut->writeln(sprintf('Component: %s', $issue->fieldIssueComponent));
        $this->stdOut->writeln(sprintf('Priority: %s', $this->getIssuePriorityLabel($issue->fieldIssuePriority)));
        $this->stdOut->writeln(sprintf('Category: %s', $this->getIssueCategoryLabel($issue->fieldIssueCategory)));
        // Assigned field does not seem to be exposed on API.
        // TODO Convert to username.
        $this->stdOut->writeln(sprintf('Reporter: %s', $issue->authorId ?? ''));
        $this->stdOut->writeln(sprintf('Created: %s', date('r', $issue->created)));
        $this->stdOut->writeln(sprintf('Updated: %s', date('r', $issue->changed)));
        $this->stdOut->writeln(sprintf("\nIssue summary:\n%s", strip_tags($issue->bodyValue ?? '')));
        return 0;
    }
}
