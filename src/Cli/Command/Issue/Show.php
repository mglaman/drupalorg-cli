<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\Issue\GetIssueAction;
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
            ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output options: text, json, md, llm. Defaults to text.',
                'text'
            )
            ->setDescription('Show a given issue information.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nid = $this->stdIn->getArgument('nid');
        $result = (new GetIssueAction($this->client))($nid);
        $format = $this->stdIn->getOption('format');

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
        return 0;
    }
}
