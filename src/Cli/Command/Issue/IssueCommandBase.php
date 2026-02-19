<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use mglaman\DrupalOrg\Entity\File;
use mglaman\DrupalOrg\Entity\IssueFile;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class IssueCommandBase extends Command
{

    protected ?GitRepository $repository;

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

    protected function initialize(
        InputInterface $input,
        OutputInterface $output
    ): void {
        parent::initialize($input, $output);
        $this->initRepo();

        $this->nid = (string) $this->stdIn->getArgument('nid');
        if ($this->nid === '') {
            $this->debug(
                "Argument nid not provided. Trying to get it from current branch name."
            );
            $this->nid = $this->getNidFromBranch($this->repository);
            if ($this->nid === '') {
                $this->stdErr->writeln(
                    "Argument nid not provided and not able to get it from current branch name - aborting."
                );
                exit(1);
            }
        }
    }

    /**
     * Initializes repository for current directory.
     */
    protected function initRepo(): void
    {
        if (isset($this->repository)) {
            $this->debug("Repository already initialized.");
            return;
        }


        try {
            $process = new Process(['git', 'rev-parse', '--show-toplevel']);
            $process->run();
            $repository_dir = trim($process->getOutput());
            $this->cwd = $repository_dir;
            $client = new Git();
            $this->repository = $client->open($this->cwd);
        } catch (\Exception $e) {
            $this->stdErr->writeln("No repository found in current directory.");
            exit(1);
        }
    }

    /**
     * Get the issue version's branch name.
     *
     * @param \mglaman\DrupalOrg\Entity\IssueNode $issue
     *   The issue node entity
     *
     * @return string
     *   The branch name.
     */
    protected function getIssueVersionBranchName(IssueNode $issue): string
    {
        $issue_version_branch = $issue->fieldIssueVersion;
        if ($issue->fieldProjectId === '3060') {
            return substr($issue_version_branch, 0, 5);
        }
        // Issue versions following semantic versioning (e.g. 1.0.0-x, 1.0.x-dev,
        // 2.0.0-alpha1). Extract major.minor and append .x for the branch name.
        if (preg_match('/^(\d+\.\d+)\./', $issue_version_branch, $matches)) {
            return $matches[1] . '.x';
        }
        // Issue versions can be 8.x-1.0-rc1, 8.x-1.x-dev, 8.x-2.0. So we get the
        // first section to find the development branch. This will give us a
        // branch in the format of: 8.x-1.x, for example.
        return substr($issue_version_branch, 0, 6) . 'x';
    }

    /**
     * Gets a clean version of the issue title.
     *
     * @param \mglaman\DrupalOrg\Entity\IssueNode $issue
     *   The issue node entity.
     *
     * @return string
     *   The formatted title.
     */
    protected function getCleanIssueTitle(IssueNode $issue): string
    {
        $cleanTitle = preg_replace(
            '/[^a-zA-Z0-9]+/',
            '_',
            $issue->title
        );
        $cleanTitle = strtolower(substr($cleanTitle, 0, 20));
        $cleanTitle = preg_replace('/(^_|_$)/', '', $cleanTitle);
        return $cleanTitle;
    }

    /**
     * Builds a branch name for an issue.
     *
     * @param \mglaman\DrupalOrg\Entity\IssueNode $issue
     *   The issue node entity.
     *
     * @return string
     *   The branch name.
     */
    protected function buildBranchName(IssueNode $issue): string
    {
        $cleanTitle = $this->getCleanIssueTitle($issue);
        return sprintf('%s-%s', $issue->nid, $cleanTitle);
    }

    /**
     * Gets the latest patch file item from an issue.
     */
    protected function getLatestFile(IssueNode $issue): ?File
    {
        // Remove files hidden from display.
        $files = array_filter(
            $issue->fieldIssueFiles,
            static function (IssueFile $value): bool {
                return $value->display;
            }
        );
        // Reverse the array so we fetch latest files first.
        $files = array_reverse($files);
        $files = array_map(
            function (IssueFile $value): File {
                return $this->client->getFile($value->fileId);
            },
            $files
        );
        // Filter out non-patch files.
        $files = array_filter(
            $files,
            static function (File $file): bool {
                return str_contains($file->name, '.patch') && !str_contains($file->name, 'do-not-test');
            }
        );
        return count($files) > 0 ? reset($files) : null;
    }

    /**
     * Gets nid from head / branch name.
     *
     * @param \CzProject\GitPhp\GitRepository $repo
     *   The repository.
     *
     * @return string
     *   The node id.
     */
    protected function getNidFromBranch(GitRepository $repo): string
    {
        $branch = $repo->getCurrentBranchName();
        return (preg_match('/(\d+)-/', $branch, $matches) ? $matches[1] : '');
    }
}
