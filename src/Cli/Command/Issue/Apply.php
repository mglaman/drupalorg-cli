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
    // Validate the issue versions branch, create or checkout issue branch.
    $issueBranchCommand = $this->getApplication()->find('issue:branch');
    $issueBranchCommand->run($this->stdIn, $this->stdOut);

    $nid = $this->stdIn->getArgument('nid');
    $issue = $this->getNode($nid);

    $patchFileUrl = $this->getPatchFileUrl($issue);
    $patchFileContents = file_get_contents($patchFileUrl);
    $patchFileName = $this->getCleanIssueTitle($issue) . 'merge-temp.patch';
    file_put_contents($patchFileName, $patchFileContents);

    $branchName = $this->buildBranchName($issue);
    $tempBranchName = $branchName . '-patch-temp';

    // Check out the root development branch to create a temporary merge branch
    // where we will apply the patch, and then three way merge to existing issue
    // branch.
    $issueVersionBranch = $this->getIssueVersionBranchName($issue);
    $this->repository->checkout($issueVersionBranch);
    $this->stdOut->writeln(sprintf('<comment>%s</comment>', "Creating temp branch $tempBranchName"));
    $this->repository->createBranch($tempBranchName);
    $this->repository->checkout($tempBranchName);

    $process = new Process(sprintf('git apply -v --index %s', $patchFileName));
    $process->run();

    if ($process->getExitCode() != 0) {
      $this->stdOut->writeln('<error>Failed to apply the patch</error>');
      $this->stdOut->writeln($process->getOutput());
      return;
    }
    $this->stdOut->writeln(sprintf('<comment>%s</comment>', "Committing $patchFileUrl"));
    $this->repository->commit($patchFileUrl);

    // Check out existing issue branch for three way merge.
    $this->stdOut->writeln(sprintf('<comment>%s</comment>', "Checking out $branchName and merging"));
    $this->repository->checkout($branchName);
    $merge = new Process(sprintf('git merge %s --strategy recursive -X theirs', $tempBranchName));
    $merge->run();

    if ($process->getExitCode() != 0) {
      $this->stdOut->writeln('<error>Failed to apply the patch</error>');
      $this->stdOut->writeln($process->getOutput());
      return;
    }

    $deleteTempBranch = new Process(sprintf('git branch -D %s', $tempBranchName));
    $deleteTempBranch->run();
  }

  protected function getPatchFileUrl(RawResponse $issue) {
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
    return $patchFileUrl;
  }

}
