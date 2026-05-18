<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrg\Result\GitLab\GitLabIssueResult;

class GetGitLabIssueAction
{
    public function __construct(private readonly GitLabClient $gitLabClient)
    {
    }

    public function __invoke(WorkItemRef $ref): GitLabIssueResult
    {
        $data = $this->gitLabClient->getIssue($ref->projectPath, $ref->issueId);
        return new GitLabIssueResult(GitLabIssue::fromStdClass($data));
    }
}
