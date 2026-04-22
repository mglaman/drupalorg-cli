<?php

namespace mglaman\DrupalOrg\Action\Issue;

use CzProject\GitPhp\GitRepository;
use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\File;
use mglaman\DrupalOrg\Entity\IssueFile;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Result\Issue\GenerateIssueInterdiffResult;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GenerateIssueInterdiffAction implements ActionInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly GitRepository $repository,
        private readonly string $cwd,
    ) {
    }

    public function __invoke(string $nid): GenerateIssueInterdiffResult
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

        // Find the two last commits on the issue branch.
        $logProcess = new Process(
            ['git', 'log', '-2', "$issueVersionBranch..HEAD", '--format=%H']
        );
        $logProcess->setWorkingDirectory($this->cwd);
        try {
            $logProcess->mustRun();
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException('Failed to read git log: ' . $e->getMessage(), 0, $e);
        }
        $commits = array_values(array_filter(array_map('trim', explode(PHP_EOL, $logProcess->getOutput()))));

        if (count($commits) !== 2) {
            throw new \RuntimeException('Too few commits on issue branch to create interdiff.');
        }

        // git log outputs newest-first; reverse to get [older, newer] order.
        $commits = array_reverse($commits);

        $diffProcess = new Process(['git', 'diff', $commits[0], $commits[1]]);
        $diffProcess->setWorkingDirectory($this->cwd);
        try {
            $diffProcess->mustRun();
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException('Failed to generate interdiff: ' . $e->getMessage(), 0, $e);
        }

        $interdiffName = $this->buildInterdiffName($issue);
        $interdiffPath = $this->cwd . DIRECTORY_SEPARATOR . $interdiffName;
        file_put_contents($interdiffPath, $diffProcess->getOutput());

        $statProcess = new Process(['git', 'diff', $commits[0], $commits[1], '--stat']);
        $statProcess->setWorkingDirectory($this->cwd);
        $statProcess->run();

        return new GenerateIssueInterdiffResult(
            interdiffName: $interdiffName,
            interdiffPath: $interdiffPath,
            diffStat: $statProcess->getOutput(),
        );
    }

    private function buildInterdiffName(IssueNode $issue): string
    {
        $lastCommentWithPatch = $this->getLastCommentWithPatch($issue);
        return sprintf(
            'interdiff-%s-%s-%s.txt',
            $issue->nid,
            $lastCommentWithPatch,
            $issue->commentCount + 1
        );
    }

    private function getLastCommentWithPatch(IssueNode $issue): int
    {
        $commentIndex = $this->buildCommentIndex($issue);
        $cid = $this->getLatestFileCid($issue);
        return $commentIndex[$cid] ?? 1;
    }

    /**
     * Builds an index of comment positions (1-based) keyed by comment ID.
     *
     * @return array<int, int>
     */
    private function buildCommentIndex(IssueNode $issue): array
    {
        $commentIndex = [];
        foreach ($issue->comments as $index => $comment) {
            $commentIndex[(int) $comment->id] = $index + 1;
        }
        return $commentIndex;
    }

    private function getLatestFileCid(IssueNode $issue): int
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

    private function getLatestFile(IssueNode $issue): ?File
    {
        $files = array_filter(
            $issue->fieldIssueFiles,
            static function (IssueFile $value): bool {
                return $value->display;
            }
        );
        $files = array_reverse($files);
        $files = array_map(
            function (IssueFile $value): File {
                return $this->client->getFile($value->fileId);
            },
            $files
        );
        $files = array_filter(
            $files,
            static function (File $file): bool {
                return str_contains($file->name, '.patch') && !str_contains($file->name, 'do-not-test');
            }
        );
        return count($files) > 0 ? reset($files) : null;
    }
}
