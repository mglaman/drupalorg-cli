<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

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
        ->setHelp(implode(PHP_EOL, [
            'This command creates a branch for the issue.',
            'If there is an existing patch in the issue queue, that you want to apply,',
            'it is quicker to use the \'issue:apply\' command instead.']));
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $issue = $this->getNode($this->nid);
        $branchName = $this->buildBranchName($issue);

        $issueVersionBranch = $this->getIssueVersionBranchName($issue);
        if (!$this->repository->hasBranch($issueVersionBranch)) {
            $this->stdOut->writeln(sprintf('<error>The issue version branch %s is not available.</error>', $issueVersionBranch));
            return 1;
        }
        $this->stdOut->writeln(sprintf('<info>Creating issue branch for %s</info>', $issueVersionBranch));
        $this->repository->checkout($issueVersionBranch);

        if ($this->repository->hasBranch($branchName)) {
            $this->stdOut->writeln(sprintf('<info>The branch %s exists! Checking it out</info>', $branchName));
            $this->repository->checkout($branchName);
        } else {
            $this->stdOut->writeln(sprintf('<info>Creating the %s branch. Checking it out</info>', $branchName));
            $this->repository->createBranch($branchName);
            $this->repository->checkout($branchName);
        }
        return 0;
    }
}
