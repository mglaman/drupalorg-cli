<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\Issue\GetIssueBranchNameAction;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Branch extends IssueCommandBase
{

    protected function configure(): void
    {
        $this
            ->setName('issue:branch')
            ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID')
            ->setDescription('Creates a branch for the issue.')
            ->setHelp(
                implode(
                    PHP_EOL,
                    [
                        'This command creates a branch for the issue.',
                        'If there is an existing patch in the issue queue, that you want to apply,',
                        'it is quicker to use the \'issue:apply\' command instead.',
                    ]
                )
            );
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $action = new GetIssueBranchNameAction($this->client);
        $result = $action($this->nid);

        if (!in_array($result->issueVersionBranch, $this->repository->getBranches(), true)) {
            $this->stdOut->writeln(
                sprintf(
                    '<error>The issue version branch %s is not available.</error>',
                    $result->issueVersionBranch
                )
            );
            return 1;
        }
        $this->stdOut->writeln(
            sprintf(
                '<info>Creating issue branch for %s</info>',
                $result->issueVersionBranch
            )
        );
        $this->repository->checkout($result->issueVersionBranch);

        if (in_array($result->branchName, $this->repository->getBranches(), true)) {
            $this->stdOut->writeln(
                sprintf(
                    '<info>The branch %s exists! Checking it out</info>',
                    $result->branchName
                )
            );
            $this->repository->checkout($result->branchName);
        } else {
            $this->stdOut->writeln(
                sprintf(
                    '<info>Creating the %s branch. Checking it out</info>',
                    $result->branchName
                )
            );
            $this->repository->createBranch($result->branchName, true);
        }
        return 0;
    }
}
