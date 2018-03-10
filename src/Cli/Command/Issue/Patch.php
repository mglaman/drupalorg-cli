<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use Gitter\Client;
use mglaman\DrupalOrg\RawResponse;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Patch extends Command {

  /**
   * @var \Gitter\Repository
   */
  protected $repository;

  protected $cwd;

  protected function configure()
  {
    $this
      ->setName('issue:patch')
      ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID')
      ->setDescription('Opens project kanban');
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

    $patchName = $this->buildPatchName($issue);

    if ($this->checkBranch($issue)) {
      $issue_version_branch = $issue->get('field_issue_version');
      // Issue versions can be 8.x-1.0-rc1, 8.x-1.x-dev, 8.x-2.0. So we get the
      // first section to find the development branch. This will give us a
      // branch in the format of: 8.x-1.x, for example.
      $issue_version_branch = substr($issue_version_branch, 0, 6) . 'x';
      if (!$this->repository->hasBranch($issue_version_branch)) {
        $this->stdErr->writeln("Issue branch $issue_version_branch does not exist locally.");
      }

      // Create a diff from our merge-base commit.
      $merge_base_cmd = sprintf('$(git merge-base %s HEAD)', $issue_version_branch);
      $process = new Process(sprintf('git diff --no-prefix --no-ext-diff %s HEAD', $merge_base_cmd));
      $process->run();

      $filename = $this->cwd . DIRECTORY_SEPARATOR . $patchName;
      file_put_contents($filename, $process->getOutput());
      $this->stdOut->writeln("<comment>Patch written to {$filename}</comment>");

      $process = new Process(sprintf('git diff %s --stat', $merge_base_cmd));
      $process->setTty(true);
      $process->run();
      $this->stdOut->write($process->getOutput());
    }
  }

  protected function buildPatchName(RawResponse $issue) {
    $cleanTitle = preg_replace('/[^a-zA-Z0-9]+/', '_', $issue->get('title'));
    $cleanTitle = strtolower(substr($cleanTitle, 0, 20));
    $cleanTitle = preg_replace('/(^_|_$)/', '', $cleanTitle);

    return sprintf('%s-%s-%s.patch', $cleanTitle, $issue->get('nid'), ($issue->get('comment_count') + 1));
  }

  protected function checkBranch(RawResponse $issue) {
    $issueVersion = $issue->get('field_issue_version');
    if (strpos($issueVersion, $this->repository->getCurrentBranch()) !== FALSE) {
      $this->stdOut->writeln("<comment>You do not appear to be working on an issue branch.</comment>");
      return false;
    }
    return true;
  }
}
