<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\Result\GitLab\GitLabIssuesResult;
use Symfony\Component\Process\Process;

class ListGitLabIssuesAction
{
    public function __invoke(string $projectMachineName, string $state = 'opened', int $limit = 25): GitLabIssuesResult
    {
        $cmd = [
            'glab', 'issue', 'list',
            '-R', 'project/' . $projectMachineName,
            '-O', 'json',
            '-P', (string) $limit,
        ];
        if ($state === 'closed') {
            $cmd[] = '--closed';
        } elseif ($state === 'all') {
            $cmd[] = '--all';
        }

        $process = new Process($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                sprintf(
                    'glab issue list failed for project/%s: %s',
                    $projectMachineName,
                    trim($process->getErrorOutput())
                )
            );
        }

        $data = json_decode($process->getOutput(), false, 512, JSON_THROW_ON_ERROR);
        $issues = array_map(
            static fn(\stdClass $item) => GitLabIssue::fromStdClass($item),
            is_array($data) ? $data : []
        );

        return new GitLabIssuesResult(
            projectMachineName: $projectMachineName,
            issues: $issues,
        );
    }
}
