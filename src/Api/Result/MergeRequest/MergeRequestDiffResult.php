<?php

namespace mglaman\DrupalOrg\Result\MergeRequest;

use mglaman\DrupalOrg\Result\ResultInterface;

class MergeRequestDiffResult implements ResultInterface
{
    public function __construct(
        public readonly int $iid,
        public readonly string $sourceBranch,
        public readonly string $targetBranch,
        public readonly string $diff,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'iid' => $this->iid,
            'source_branch' => $this->sourceBranch,
            'target_branch' => $this->targetBranch,
            'diff' => $this->diff,
        ];
    }
}
