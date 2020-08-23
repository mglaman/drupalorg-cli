<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\RawResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Patch extends IssueCommandBase
{

  /**
   * @var \Gitter\Repository
   */
    protected $repository;

    protected $cwd;

    protected function configure()
    {
        $this
        ->setName('issue:patch')
        ->setAliases(['ip'])
        ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
        ->setDescription('Generate a patch for the issue from committed local changes.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        if ($this->nid != $this->getNidFromBranch($this->repository)) {
            $this->stdErr->writeln("NID from argument is different from NID in issue branch name.");
            exit(1);
        }
    }

  /**
   * {@inheritdoc}
   *
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $issue = $this->getNode($this->nid);

        $patchName = $this->buildPatchName($issue);

        if ($this->checkBranch($issue)) {
            $issue_version_branch = $this->getIssueVersionBranchName($issue);
            if (!$this->repository->hasBranch($issue_version_branch)) {
                $this->stdErr->writeln("Issue branch $issue_version_branch does not exist locally.");
                exit(1);
            }

          // Create a diff from our merge-base commit.
            $merge_base_cmd = sprintf('$(git merge-base %s HEAD)', $issue_version_branch);
            $process = new Process(sprintf('git diff --no-ext-diff %s HEAD', $merge_base_cmd));
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

    protected function buildPatchName(RawResponse $issue)
    {
        $cleanTitle = $this->getCleanIssueTitle($issue);
        return sprintf('%s-%s-%s.patch', $cleanTitle, $issue->get('nid'), ($issue->get('comment_count') + 1));
    }

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
