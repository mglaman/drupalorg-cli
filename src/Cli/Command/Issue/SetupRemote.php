<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\Issue\SetupIssueRemoteAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetupRemote extends IssueCommandBase
{
    protected function configure(): void
    {
        $this
            ->setName('issue:setup-remote')
            ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
            ->setDescription('Add the GitLab issue fork as a git remote and fetch it.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = new SetupIssueRemoteAction($this->client, new GitLabClient());
        $result = $action($this->nid);

        if ($result->alreadyExists) {
            $this->stdOut->writeln(
                sprintf('<comment>Remote %s already exists.</comment>', $result->remoteName)
            );
        } else {
            $this->stdOut->writeln(
                sprintf('<info>Remote %s added.</info>', $result->remoteName)
            );
        }

        if ($result->fetchOutput !== '') {
            $this->stdOut->writeln($result->fetchOutput);
        }

        return 0;
    }
}
