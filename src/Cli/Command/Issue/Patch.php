<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\RawResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Patch extends IssueCommandBase
{

    protected string $cwd;

    protected function configure(): void
    {
        $this
            ->setName('issue:patch')
            ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
            ->setDescription(
                'Generate a patch for the issue from committed local changes.'
            );
    }

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
     *
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $issue = $this->client->getNode($this->nid);

        $patchName = $this->buildPatchName($issue);

        if ($this->checkBranch($issue)) {
            $issue_version_branch = $this->getIssueVersionBranchName($issue);
            if (!in_array($issue_version_branch, $this->repository->getBranches(), true)) {
                $this->stdErr->writeln(
                    "Issue branch $issue_version_branch does not exist locally."
                );
                exit(1);
            }

            // Create a diff from our merge-base commit.
            $merge_base_cmd = sprintf(
                '$(git merge-base %s HEAD)',
                $issue_version_branch
            );
            $process = new Process(
                ['git', 'diff', '--no-ext-diff', $merge_base_cmd, 'HEAD']
            );
            $process->run();

            $filename = $this->cwd . DIRECTORY_SEPARATOR . $patchName;
            file_put_contents($filename, $process->getOutput());
            $this->stdOut->writeln(
                "<comment>Patch written to {$filename}</comment>"
            );

            $process = new Process(['git', 'diff', $merge_base_cmd, '--stat']);
            $process->setTty(true);
            $process->run();
            $this->stdOut->write($process->getOutput());
        }
        return 0;
    }

    protected function buildPatchName(RawResponse $issue): string
    {
        $cleanTitle = $this->getCleanIssueTitle($issue);
        return sprintf(
            '%s-%s-%s.patch',
            $cleanTitle,
            $issue->get('nid'),
            ($issue->get('comment_count') + 1)
        );
    }

    protected function checkBranch(RawResponse $issue): bool
    {
        $issueVersion = $issue->get('field_issue_version');
        if (strpos(
            $issueVersion,
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
