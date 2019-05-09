<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use GitWrapper\GitWorkingCopy;
use mglaman\DrupalOrgCli\Git;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Branch extends IssueCommandBase
{

    protected function configure()
    {
        $this
        ->setName('issue:branch')
        ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID')
        ->setDescription('Creates a branch for the issue.');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $git = new Git();
        $workingCopy = $git->getWorkingCopy(getcwd());
        if (!$workingCopy instanceof GitWorkingCopy) {
            $output->writeln('<info>You must be in a repository</info>');
            return 1;
        }

        $nid = $this->stdIn->getArgument('nid');
        $issue = $this->getNode($nid);
        $branchName = $this->buildBranchName($issue);

        $issueVersionBranch = $this->getIssueVersionBranchName($issue);

        $branches = $workingCopy->getBranches()->all();

        if (!in_array($issueVersionBranch, $branches, true)) {
            $this->stdOut->writeln(sprintf('<error>The issue version branch %s is not available.</error>', $issueVersionBranch));
            return 1;
        }
        $this->stdOut->writeln(sprintf('<info>Checking issue branch for %s</info>', $issueVersionBranch), OutputInterface::VERBOSITY_DEBUG);
        $workingCopy->checkout($issueVersionBranch);

        if (in_array($branchName, $branches, true)) {
            $this->stdOut->writeln(sprintf('Checkout out existing <info>%s</info> branch', $branchName));
        } else {
            $this->stdOut->writeln(sprintf('Checking out the new <info>%s</info> branch.', $branchName));
            $workingCopy->checkoutNewBranch($branchName);
        }
        $workingCopy->checkout($branchName);
        return 0;
    }
}
