<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use Gitter\Repository;
use Gitter\Client;
use mglaman\DrupalOrg\RawResponse;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class IssueCommandBase extends Command
{

    protected ?Repository $repository;

  /**
   * The current working directory.
   *
   * @var string
   */
    protected string $cwd;

  /**
   * The issue node ID.
   *
   * @var string
   */
    protected string $nid;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->initRepo();

        $this->nid = $this->stdIn->getArgument('nid');
        if ($this->nid === null) {
            $this->debug("Argument nid not provided. Trying to get it from current branch name.");
            $this->nid = $this->getNidFromBranch($this->repository);
            if ($this->nid === null) {
                $this->stdErr->writeln("Argument nid not provided and not able to get it from current branch name - aborting.");
                exit(1);
            }
        }
    }

  /**
   * Initializes repository for current directory.
   */
    protected function initRepo()
    {
        if ($this->repository !== null) {
            $this->debug("Repository already initialized.");
            return;
        }


        try {
            $process = new Process('git rev-parse --show-toplevel');
            $process->run();
            $repository_dir = trim($process->getOutput());
            $this->cwd = $repository_dir;
            $client = new Client();
            $this->repository = $client->getRepository($this->cwd);
        } catch (\Exception $e) {
            $this->stdErr->writeln("No repository found in current directory.");
            exit(1);
        }
    }

  /**
   * Get the issue version's branch name.
   *
   * @param \mglaman\DrupalOrg\RawResponse $issue
   *   The issue raw response
   *
   * @return string
   *   The branch name.
   */
    protected function getIssueVersionBranchName(RawResponse $issue): string {
        $issue_version_branch = $issue->get('field_issue_version');
        if ($issue->get('field_project')->id === '3060') {
            return substr($issue_version_branch, 0, 5);
        }
      // Issue versions can be 8.x-1.0-rc1, 8.x-1.x-dev, 8.x-2.0. So we get the
      // first section to find the development branch. This will give us a
      // branch in the format of: 8.x-1.x, for example.
        return substr($issue_version_branch, 0, 6) . 'x';
    }

  /**
   * Gets a clean version of the issue title.
   *
   * @param \mglaman\DrupalOrg\RawResponse $issue
   *   The issue raw response.
   *
   * @return string
   *   The formatted title.
   */
    protected function getCleanIssueTitle(RawResponse $issue): string {
        $cleanTitle = preg_replace('/[^a-zA-Z0-9]+/', '_', $issue->get('title'));
        $cleanTitle = strtolower(substr($cleanTitle, 0, 20));
        $cleanTitle = preg_replace('/(^_|_$)/', '', $cleanTitle);
        return $cleanTitle;
    }

  /**
   * Builds a branch name for an issue.
   *
   * @param \mglaman\DrupalOrg\RawResponse $issue
   *   The raw response.
   *
   * @return string
   *   The branch name.
   */
    protected function buildBranchName(RawResponse $issue): string {
        $cleanTitle = $this->getCleanIssueTitle($issue);
        return sprintf('%s-%s', $issue->get('nid'), $cleanTitle);
    }

  /**
   * Gets the latest patch file item from an issue.
   */
    protected function getLatestFile(RawResponse $issue): ?RawResponse
    {
      // Remove files hidden from display.
        $files = array_filter($issue->get('field_issue_files'), static function ($value): bool {
            return (bool) $value->display;
        });
      // Reverse the array so we fetch latest files first.
        $files = array_reverse($files);
        $files = array_map(function ($value): RawResponse {
            return $this->getFile($value->file->id);
        }, $files);
      // Filter out non-patch files.
        $files = array_filter($files, static function (RawResponse $file): bool {
            return strpos($file->get('name'), '.patch') !== false && strpos($file->get('name'), 'do-not-test') === false;
        });
        return count($files) > 0 ? reset($files) : null;
    }

  /**
   * Gets nid from head / branch name.
   *
   * @param \Gitter\Repository $repo
   *   The repository.
   *
   * @return string
   *   The node id.
   */
    protected function getNidFromBranch(Repository $repo): ?string {
        $branch = $repo->getHead();
        return (preg_match('/(\d+)-/', $branch, $matches) ?  $matches[1] : null);
    }
}
