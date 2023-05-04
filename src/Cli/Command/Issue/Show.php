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
        $issue_data = $issue->getContent();
        $format = $this->stdIn->getOption('format');

        if ($format == 'json') {
            $this->stdOut->writeln(json_encode($issue_data));
            return 0;
        }
        // format option is text.
        $this->stdOut->writeln(sprintf('Title: %s', $issue->get('title')));
        $this->stdOut->writeln(sprintf('Status: %s', $this->getIssueStatusLabel($issue->get('field_issue_status'))));
        $this->stdOut->writeln(sprintf('Project: %s', $issue_data->field_project->machine_name));
        $this->stdOut->writeln(sprintf('Version: %s', $issue->get('field_issue_version')));
        $this->stdOut->writeln(sprintf('Component: %s', $issue->get('field_issue_component')));
        $this->stdOut->writeln(sprintf('Priority: %s', $this->getIssuePriorityLabel($issue->get('field_issue_priority'))));
        $this->stdOut->writeln(sprintf('Category: %s', $this->getIssueCategoryLabel($issue->get('field_issue_category'))));
        // Assigned field does not seem to be exposed on API.
        // TODO Convert to username.
        $this->stdOut->writeln(sprintf('Reporter: %s', $issue_data->author->id));
        $this->stdOut->writeln(sprintf('Created: %s', date('r', $issue->get('created'))));
        $this->stdOut->writeln(sprintf('Updated: %s', date('r', $issue->get('changed'))));
        $this->stdOut->writeln(sprintf("\nIssue summary:\n%s", strip_tags($issue_data->body->value)));
        return 0;
    }
}
