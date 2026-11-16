<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\GitLab\GetIssueTemplateAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Template extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('issue:template')
            ->addArgument('project', InputArgument::REQUIRED, 'Project machine name (e.g. tool or project/tool)')
            ->addArgument('name', InputArgument::OPTIONAL, 'Template name. Defaults to Default, the one GitLab applies to issues created without a description.')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format: text, json, md, llm.', 'text')
            ->setDescription('Print a project\'s issue description template, to fill in for issue:create.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = (string) $this->stdIn->getArgument('project');
        $name = $this->stdIn->getArgument('name');

        $action = new GetIssueTemplateAction($this->client, $this->createGitLabClient());
        $result = $action($project, is_string($name) && $name !== '' ? $name : null);

        if ($this->writeFormatted($result, (string) $this->stdIn->getOption('format'))) {
            return 0;
        }
        if ($result->name === null) {
            $this->stdErr->writeln(sprintf(
                '%s has no Default issue template.%s',
                $result->projectPath,
                $result->available === [] ? '' : ' Available: ' . implode(', ', $result->available) . '.',
            ));
            return 0;
        }
        // Raw, so the output can be redirected to a file and edited.
        $this->stdOut->write($result->content, false, OutputInterface::OUTPUT_RAW);
        return 0;
    }

    protected function createGitLabClient(): GitLabClient
    {
        return new GitLabClient();
    }
}
