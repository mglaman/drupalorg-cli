<?php

namespace mglaman\DrupalOrg\Result\MergeRequest;

use mglaman\DrupalOrg\Result\ResultInterface;

class MergeRequestListResult implements ResultInterface
{
    /**
     * @param MergeRequestItem[] $mergeRequests
     */
    public function __construct(
        public readonly string $projectPath,
        public readonly array $mergeRequests,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project_path' => $this->projectPath,
            'merge_requests' => array_map(
                static fn(MergeRequestItem $mr) => $mr->toArray(),
                $this->mergeRequests
            ),
        ];
    }
}
