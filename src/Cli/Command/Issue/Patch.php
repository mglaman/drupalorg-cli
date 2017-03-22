<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use Gitter\Client;
use mglaman\DrupalOrg\RawResponse;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Patch extends Command {

  /**
   * @var \Gitter\Repository
   */
  protected $repository;

  protected function configure()
  {
    $this
      ->setName('issue:patch')
      ->addArgument('nid', InputArgument::REQUIRED, 'The project machine name')
      ->setDescription('Opens project kanban');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    try {
      $client = new Client();
      $this->repository = $client->getRepository(getcwd());
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
    $output->writeln("<info>Patch name: $patchName");

    if ($this->checkBranch($issue)) {

    }
  }

  protected function buildPatchName(RawResponse $issue) {
    $cleanTitle = preg_replace('/[^a-zA-Z0-9]+/', '_', $issue->get('title'));
    $cleanTitle = strtolower(substr($cleanTitle, 0, 20));
    $cleanTitle = preg_replace('/(^_|_$)/', '', $cleanTitle);

    return implode('_', [
      $cleanTitle,
      $issue->get('nid'),
      $issue->get('comment_count') + 1
    ]) . '.patch';
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