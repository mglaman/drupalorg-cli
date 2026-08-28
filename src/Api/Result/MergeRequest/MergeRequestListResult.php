<?php

namespace mglaman\DrupalOrg\Result\MergeRequest;

use mglaman\DrupalOrg\Result\ResultInterface;

class MergeRequestListResult implements ResultInterface
{
    /**
     * @param MergeRequestItem[] $mergeRequests
     * @param string|null $issueFork
     *   The issue fork path the list is scoped to, or null for a
     *   project-wide list.
     */
    public function __construct(
        public readonly string $projectPath,
        public readonly array $mergeRequests,
        public readonly ?string $issueFork = null,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project_path' => $this->projectPath,
            'issue_fork' => $this->issueFork,
            'merge_requests' => array_map(
                static fn(MergeRequestItem $mr) => $mr->toArray(),
                $this->mergeRequests
            ),
        ];
    }
}
