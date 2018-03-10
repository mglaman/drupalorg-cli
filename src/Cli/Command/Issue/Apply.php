<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use Gitter\Client;
use mglaman\DrupalOrg\RawResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Apply extends IssueCommandBase {

  /**
   * @var \Gitter\Repository
   */
  protected $repository;

  protected $cwd;

  protected function configure()
  {
    $this
      ->setName('issue:apply')
      ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID')
      ->setDescription('Applies the latest patch from an issue.');
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

    $issueBranchCommand = $this->getApplication()->find('issue:branch');
    $issueBranchCommand->run($this->stdIn, $this->stdOut);

    $nid = $this->stdIn->getArgument('nid');
    $issue = $this->getNode($nid);
    // Remove files hidden from display.
    $files = array_filter($issue->get('field_issue_files'), function ($value) {
      return (bool) $value->display;
    });
    // Reverse the array so we fetch latest files first.
    $files = array_reverse($files);
    $files = array_map(function ($value) {
      return $this->getFile($value->file->id);
    }, $files);
    // Filter out non-patch files.
    $files = array_filter($files, function (RawResponse $file) {
      return strpos($file->get('name'), '.patch') !== FALSE && strpos($file->get('name'), 'do-not-test') === FALSE;
    });
    $patchFile = reset($files);
    $patchFileUrl = $patchFile->get('url');

    $process = new Process(sprintf('curl %s | git apply -v', $patchFileUrl));
    $process->run();

    if ($process->getExitCode() != 0) {
      $this->stdOut->writeln('<error>Failed to apply the patch</error>');
      $this->stdOut->writeln($process->getOutput());
    } else {
      $process = new Process('git diff');
      $process->setTty(true);
      $process->run();
      $this->stdOut->write($process->getOutput());

      $filesToCommit = new Process('git ls-files --others --modified --exclude=*.patch');
      $filesToCommit->run();
      $filesToCommit = array_filter(explode(PHP_EOL, trim($filesToCommit->getOutput())));
      $this->repository->add($filesToCommit);
      $this->repository->commit($patchFileUrl);
    }
  }

}
