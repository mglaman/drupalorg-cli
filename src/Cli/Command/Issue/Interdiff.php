<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use Gitter\Client;
use mglaman\DrupalOrg\RawResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Command to generate an interdiff text file for an issue.
 */
class Interdiff extends IssueCommandBase {

  /**
   * The git repository.
   *
   * @var \Gitter\Repository
   */
  protected $repository;

  /**
   * The current working directory.
   *
   * @var string
   */
  protected $cwd;

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('issue:interdiff')
      ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID')
      ->setDescription('Generate an interdiff for the issue from local changes.');
  }

  /**
   * {@inheritdoc}
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    $this->cwd = getcwd();
    try {
      $client = new Client();
      $this->repository = $client->getRepository($this->cwd);
    }
    catch (\Exception $e) {
      $this->repository = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $nid = $this->stdIn->getArgument('nid');
    $issue = $this->getNode($nid);

    if (!$this->checkBranch($issue)) {
      return;
    }

    $issue_version_branch = $this->getIssueVersionBranchName($issue);
    if (!$this->repository->hasBranch($issue_version_branch)) {
      $this->stdErr->writeln("Issue branch $issue_version_branch does not exist locally.");
    } else {
      $this->debug("Using issue branch $issue_version_branch.");
    }

    // Find the two last commits on the issue branch.
    $process = new Process(sprintf('git log -2 %s..HEAD --format=%%H', $issue_version_branch));
    $process->run();
    $last_issue_branch_commits = explode(PHP_EOL, $process->getOutput());
    array_pop($last_issue_branch_commits); // Empty item because line breaks from "git log" output.
    if (count($last_issue_branch_commits) != 2) {
      $this->stdErr->writeln("Too few commits on issue branch to create interdiff.");
      exit(1);
    }

    // Create a diff between two last commits of the issue branch. (Reverse order of output from "git log".)
    $diff_cmd = sprintf('git diff %s', implode(" ", array_reverse($last_issue_branch_commits)));
    $process = new Process($diff_cmd);
    $process->run();
    $filename = $this->cwd . DIRECTORY_SEPARATOR . $this->buildInterdiffName($issue);
    file_put_contents($filename, $process->getOutput());
    $this->stdOut->writeln("<comment>Interdiff written to {$filename}</comment>");

    $process = new Process("$diff_cmd --stat");
    $process->setTty(TRUE);
    $process->run();
    $this->stdOut->write($process->getOutput());
  }

  /**
   * Generates a file name for an interdiff.
   *
   * @param \mglaman\DrupalOrg\RawResponse $issue
   *   The issue raw response.
   *
   * @return string
   *   The name of the interdiff file.
   */
  protected function buildInterdiffName(RawResponse $issue) {
    $comment_count = $issue->get('comment_count');
    $last_comment_with_patch = $this->getLastCommentWithPatch($issue);
    return sprintf('interdiff-%s-%s-%s.txt', $issue->get('nid'), $last_comment_with_patch, $comment_count + 1);
  }

  /**
   * Finds the last comment with a patch.
   *
   * @param \mglaman\DrupalOrg\RawResponse $issue
   *   The issue raw response.
   *
   * @return int
   *   The comment index number.
   */
  protected function getLastCommentWithPatch(RawResponse $issue) {
    // Files have the relevant CID info, but we need to calculate the actual
    // comment index based on that.
    $comment_index = $this->getCommentIndex($issue);
    $cid = $this->getLatestFileCid($issue);
    return $comment_index[$cid] ?? 1;
  }

  /**
   * Builds an index of comments, starting with 1, keyed by CID.
   *
   * @param \mglaman\DrupalOrg\RawResponse $issue
   *   The issue raw response.
   *
   * @return array
   *   Array of comment index numbers, indexed by comment ID.
   */
  protected function getCommentIndex(RawResponse $issue) {
    $comment_index = [];
    foreach ($issue->get('comments') as $index => $comment) {
      $comment_index[$comment->id] = $index + 1;
    }
    return $comment_index;
  }

  /**
   * Gets the most recent patch file from the issue.
   *
   * @param \mglaman\DrupalOrg\RawResponse $issue
   *   The issue raw response.
   *
   * @return int
   *   The most recent patch file's associated comment ID from the issue.
   */
  protected function getLatestFileCid(RawResponse $issue) {
    $latestPatch = $this->getLatestFile($issue);
    $fid = $latestPatch->get('fid');
    $files = array_filter($issue->get('field_issue_files'), function (\stdClass $file) use ($fid) {
      return $fid == $file->file->id;
    });
    $file = reset($files);
    return $file->file->cid ?? 0;
  }

  /**
   * Checks that the user is working on an issue branch.
   *
   * @param \mglaman\DrupalOrg\RawResponse $issue
   *   The issue raw response.
   *
   * @return bool
   *   Whether or not user is working on an issue branch.
   */
  protected function checkBranch(RawResponse $issue) {
    $issueVersion = $issue->get('field_issue_version');
    if (strpos($issueVersion, $this->repository->getCurrentBranch()) !== FALSE) {
      $this->stdOut->writeln("<comment>You do not appear to be working on an issue branch.</comment>");
      return FALSE;
    }
    return TRUE;
  }

}
