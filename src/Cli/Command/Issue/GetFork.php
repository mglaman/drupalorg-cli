<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\Issue\GetIssueForkAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetFork extends IssueCommandBase
{
    protected bool $requiresRepository = false;

    protected function configure(): void
    {
        $this
            ->setName('issue:get-fork')
            ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format: text, json, md, llm. Defaults to text.',
                'text'
            )
            ->setDescription('Show the GitLab issue fork URLs and branches.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = new GetIssueForkAction($this->client, new GitLabClient());
        $result = $action($this->nid, $this->explicitProjectMachineName());
        $format = (string) $this->stdIn->getOption('format');

        if ($this->writeFormatted($result, $format)) {
            return 0;
        }

        $this->stdOut->writeln(sprintf('Remote name: %s', $result->remoteName));
        $this->stdOut->writeln(sprintf('SSH URL:     %s', $result->sshUrl));
        $this->stdOut->writeln(sprintf('HTTPS URL:   %s', $result->httpsUrl));
        $this->stdOut->writeln(sprintf('GitLab path: %s', $result->gitLabProjectPath));

        if (!$result->exists) {
            $this->stdOut->writeln('');
            $this->stdOut->writeln('Fork does not exist yet. Create it on the issue page and click "Get push access".');
            return 0;
        }

        if ($result->branches !== []) {
            $this->stdOut->writeln('');
            $this->stdOut->writeln('Branches:');
            foreach ($result->branches as $branch) {
                $this->stdOut->writeln('  ' . $branch);
            }
        } else {
            $this->stdOut->writeln('Fork exists but has no branches yet.');
        }

        return 0;
    }
}
