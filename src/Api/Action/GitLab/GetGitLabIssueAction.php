<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrg\Result\GitLab\GitLabIssueResult;
use Symfony\Component\Process\Process;

class GetGitLabIssueAction
{
    public function __invoke(WorkItemRef $ref): GitLabIssueResult
    {
        $process = new Process([
            'glab', 'issue', 'view', (string) $ref->issueId,
            '-R', $ref->projectPath,
            '-F', 'json',
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                sprintf(
                    'glab issue view failed for %s#%d: %s',
                    $ref->projectPath,
                    $ref->issueId,
                    trim($process->getErrorOutput())
                )
            );
        }

        $data = json_decode($process->getOutput(), false, 512, JSON_THROW_ON_ERROR);
        return new GitLabIssueResult(GitLabIssue::fromStdClass($data));
    }
}
