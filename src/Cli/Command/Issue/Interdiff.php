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
class Interdiff extends IssueCommandBase
{

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
    protected function configure()
    {
        $this
        ->setName('issue:interdiff')
        ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID')
        ->setDescription('Generate an interdiff for the issue from local changes.');
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->cwd = getcwd();
        try {
            $client = new Client();
            $this->repository = $client->getRepository($this->cwd);
        } catch (\Exception $e) {
            $this->repository = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $nid = $this->stdIn->getArgument('nid');
        $issue = $this->getNode($nid);

        if (!$this->checkBranch($issue)) {
            return;
        }

        $issue_version_branch = $this->getIssueVersionBranchName($issue);
        if (!$this->repository->hasBranch($issue_version_branch)) {
            $this->stdErr->writeln("Issue branch $issue_version_branch does not exist locally.");
        }

        // Create a diff from the first commit of the issue branch.
        $first_issue_branch_commit = sprintf('$(git log %s..HEAD --format=%%H)', $issue_version_branch);
        $process = new Process(sprintf('git diff %s', $first_issue_branch_commit));
        $process->run();

        $filename = $this->cwd . DIRECTORY_SEPARATOR . $this->buildInterdiffName($issue);
        file_put_contents($filename, $process->getOutput());
        $this->stdOut->writeln("<comment>Interdiff written to {$filename}</comment>");

        $process = new Process(sprintf('git diff %s --stat', $first_issue_branch_commit));
        $process->setTty(true);
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
    protected function buildInterdiffName(RawResponse $issue)
    {
        $comment_count = $issue->get('comment_count');
        return sprintf('interdiff-%s-%s-%s.txt', $issue->get('nid'), $comment_count, $comment_count + 1);
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
    protected function checkBranch(RawResponse $issue)
    {
        $issueVersion = $issue->get('field_issue_version');
        if (strpos($issueVersion, $this->repository->getCurrentBranch()) !== false) {
            $this->stdOut->writeln("<comment>You do not appear to be working on an issue branch.</comment>");
            return false;
        }
        return true;
    }
}
