<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use Gitter\Client;
use mglaman\DrupalOrg\RawResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Branch extends IssueCommandBase {

  /**
   * @var \Gitter\Repository
   */
  protected $repository;

  protected $cwd;

  protected function configure()
  {
    $this
      ->setName('issue:branch')
      ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID')
      ->setDescription('Creates a branch for the issue.');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    $this->cwd = getcwd();
    try {
      $client = new Client();
      $this->repository = $client->getRepository($this->cwd);
    }
    catch (\Exception $e) {
      $this->repository = null;
    }
  }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    $nid = $this->stdIn->getArgument('nid');
    $issue = $this->getNode($nid);
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
  }

}
