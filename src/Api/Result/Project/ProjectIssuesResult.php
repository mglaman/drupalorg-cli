<?php

namespace mglaman\DrupalOrg\Result\Project;

use mglaman\DrupalOrg\Result\ResultInterface;

class ProjectIssuesResult implements ResultInterface
{
    /**
     * @param mixed[] $issues
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
            'issues' => $this->issues,
        ];
    }
}
