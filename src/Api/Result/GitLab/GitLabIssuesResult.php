<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Result\GitLab;

use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\Result\ResultInterface;

class GitLabIssuesResult implements ResultInterface
{
    /**
     * @param GitLabIssue[] $issues
     */
    public function __construct(
        public readonly string $projectMachineName,
        public readonly array $issues,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project' => $this->projectMachineName,
            'issues' => array_map(
                static fn(GitLabIssue $issue) => [
                    'iid' => $issue->iid,
                    'title' => $issue->title,
                    'state' => $issue->state,
                    'labels' => $issue->labels,
                    'web_url' => $issue->webUrl,
                ],
                $this->issues
            ),
        ];
    }
}
