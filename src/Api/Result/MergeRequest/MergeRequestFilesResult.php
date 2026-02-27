<?php

namespace mglaman\DrupalOrg\Result\MergeRequest;

use mglaman\DrupalOrg\Result\ResultInterface;

class MergeRequestFilesResult implements ResultInterface
{
    /**
     * @param array<int, array<string, mixed>> $files
     */
    public function __construct(
        public readonly int $iid,
        public readonly array $files,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'iid' => $this->iid,
            'files' => $this->files,
        ];
    }
}
