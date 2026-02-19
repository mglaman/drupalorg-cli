<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Entity\IssueFile;
use mglaman\DrupalOrg\Entity\IssueNode;
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
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('issue:interdiff')
            ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
            ->setDescription(
                'Generate an interdiff for the issue from committed local changes.'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(
        InputInterface $input,
        OutputInterface $output
    ): void {
        parent::initialize($input, $output);
        if ($this->nid !== $this->getNidFromBranch($this->repository)) {
            $this->stdErr->writeln(
                "NID from argument is different from NID in issue branch name."
            );
            exit(1);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $issue = $this->client->getNode($this->nid);

        if (!$this->checkBranch($issue)) {
            return 1;
        }

        $issue_version_branch = $this->getIssueVersionBranchName($issue);
        if (!in_array($issue_version_branch, $this->repository->getBranches(), true)) {
            $this->stdErr->writeln(
                "Issue branch $issue_version_branch does not exist locally."
            );
            exit(1);
        }

        // Find the two last commits on the issue branch.
        $process = new Process(
            ['git', 'log', '-2', "$issue_version_branch..HEAD", '--format=%%H']
        );
        $process->run();
        $last_issue_branch_commits = explode(PHP_EOL, $process->getOutput());
        $last_issue_branch_commits = array_filter(
            array_map('trim', $last_issue_branch_commits)
        );
        if (count($last_issue_branch_commits) != 2) {
            $this->stdErr->writeln(
                "Too few commits on issue branch to create interdiff."
            );
            exit(1);
        }

        // Create a diff between two last commits of the issue branch. (Reverse order of output from "git log".)
        $diff_cmd = sprintf(
            'git diff %s',
            implode(
                " ",
                array_reverse($last_issue_branch_commits)
            )
        );
        $process = new Process([$diff_cmd]);
        $process->run();
        $filename = $this->cwd . DIRECTORY_SEPARATOR . $this->buildInterdiffName(
            $issue
        );
        file_put_contents($filename, $process->getOutput());
        $this->stdOut->writeln(
            "<comment>Interdiff written to {$filename}</comment>"
        );

        $process = new Process([$diff_cmd, '--stat']);
        $process->setTty(true);
        $process->run();
        $this->stdOut->write($process->getOutput());
        return 0;
    }

    /**
     * Generates a file name for an interdiff.
     *
     * @param \mglaman\DrupalOrg\Entity\IssueNode $issue
     *   The issue node entity.
     *
     * @return string
     *   The name of the interdiff file.
     */
    protected function buildInterdiffName(IssueNode $issue): string
    {
        $last_comment_with_patch = $this->getLastCommentWithPatch($issue);
        return sprintf(
            'interdiff-%s-%s-%s.txt',
            $issue->nid,
            $last_comment_with_patch,
            $issue->commentCount + 1
        );
    }

    /**
     * Finds the last comment with a patch.
     *
     * @param \mglaman\DrupalOrg\Entity\IssueNode $issue
     *   The issue node entity.
     *
     * @return int
     *   The comment index number.
     */
    protected function getLastCommentWithPatch(IssueNode $issue): int
    {
        // Files have the relevant CID info, but we need to calculate the actual
        // comment index based on that.
        $comment_index = $this->getCommentIndex($issue);
        $cid = $this->getLatestFileCid($issue);
        return $comment_index[$cid] ?? 1;
    }

    /**
     * Builds an index of comments, starting with 1, keyed by CID.
     *
     * @param \mglaman\DrupalOrg\Entity\IssueNode $issue
     *   The issue node entity.
     *
     * @return array<int, int>
     *   Array of comment index numbers, indexed by comment ID.
     */
    protected function getCommentIndex(IssueNode $issue): array
    {
        $comment_index = [];
        foreach ($issue->comments as $index => $comment) {
            $comment_index[(int) $comment->id] = $index + 1;
        }
        return $comment_index;
    }

    /**
     * Gets the most recent patch file's associated comment ID from the issue.
     *
     * @param \mglaman\DrupalOrg\Entity\IssueNode $issue
     *   The issue node entity.
     *
     * @return int
     *   The most recent patch file's associated comment ID.
     */
    protected function getLatestFileCid(IssueNode $issue): int
    {
        $latestPatch = $this->getLatestFile($issue);
        if ($latestPatch === null) {
            return 0;
        }
        $fid = $latestPatch->fid;
        $issueFiles = array_filter(
            $issue->fieldIssueFiles,
            static function (IssueFile $file) use ($fid): bool {
                return $file->fileId === $fid;
            }
        );
        $issueFile = reset($issueFiles);
        return $issueFile !== false ? $issueFile->cid : 0;
    }

    /**
     * Checks that the user is working on an issue branch.
     *
     * @param \mglaman\DrupalOrg\Entity\IssueNode $issue
     *   The issue node entity.
     *
     * @return bool
     *   Whether or not user is working on an issue branch.
     */
    protected function checkBranch(IssueNode $issue): bool
    {
        if (strpos(
            $issue->fieldIssueVersion,
            $this->repository->getCurrentBranchName()
        ) !== false) {
            $this->stdOut->writeln(
                "<comment>You do not appear to be working on an issue branch.</comment>"
            );
            return false;
        }
        return true;
    }
}
