<?php

namespace mglaman\DrupalOrg\Result\Project;

use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Result\ResultInterface;

class ProjectIssuesResult implements ResultInterface
{
    /**
     * @param IssueNode[] $issues
     */
    public function __construct(
        public readonly string $projectTitle,
        public readonly array $issues,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project_title' => $this->projectTitle,
            'issues' => array_map(
                static fn(IssueNode $issue) => [
                    'nid' => $issue->nid,
                    'title' => $issue->title,
                    'field_issue_status' => $issue->fieldIssueStatus,
                ],
                $this->issues
            ),
        ];
    }
}
