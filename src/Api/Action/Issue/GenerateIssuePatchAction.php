<?php

namespace mglaman\DrupalOrg\Action\Issue;

use CzProject\GitPhp\GitRepository;
use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Result\Issue\GenerateIssuePatchResult;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GenerateIssuePatchAction implements ActionInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly GitRepository $repository,
        private readonly string $cwd,
    ) {
    }

    public function __invoke(string $nid): GenerateIssuePatchResult
    {
        $issue = $this->client->getNode($nid);

        $currentBranch = $this->repository->getCurrentBranchName();
        if (strpos($issue->fieldIssueVersion, $currentBranch) !== false) {
            throw new \RuntimeException('You do not appear to be working on an issue branch.');
        }

        $issueVersionBranch = $issue->buildIssueVersionBranch();
        $branches = $this->repository->getBranches() ?? [];
        if (!in_array($issueVersionBranch, $branches, true)) {
            throw new \RuntimeException(
                sprintf('Issue branch %s does not exist locally.', $issueVersionBranch)
            );
        }

        $mergeBaseProcess = new Process(['git', 'merge-base', $issueVersionBranch, 'HEAD']);
        $mergeBaseProcess->setWorkingDirectory($this->cwd);
        try {
            $mergeBaseProcess->mustRun();
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException('Failed to determine merge base: ' . $e->getMessage(), 0, $e);
        }
        $mergeBase = trim($mergeBaseProcess->getOutput());

        $diffProcess = new Process(['git', 'diff', '--no-ext-diff', $mergeBase, 'HEAD']);
        $diffProcess->setWorkingDirectory($this->cwd);
        try {
            $diffProcess->mustRun();
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException('Failed to generate patch diff: ' . $e->getMessage(), 0, $e);
        }

        $patchName = sprintf(
            '%s-%s-%s.patch',
            $issue->buildCleanTitle(),
            $issue->nid,
            $issue->commentCount + 1
        );
        $patchPath = $this->cwd . DIRECTORY_SEPARATOR . $patchName;
        file_put_contents($patchPath, $diffProcess->getOutput());

        $statProcess = new Process(['git', 'diff', $mergeBase, '--stat']);
        $statProcess->setWorkingDirectory($this->cwd);
        $statProcess->run();

        return new GenerateIssuePatchResult(
            patchName: $patchName,
            patchPath: $patchPath,
            diffStat: $statProcess->getOutput(),
        );
    }
}
