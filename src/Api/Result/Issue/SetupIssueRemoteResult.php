<?php

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Result\ResultInterface;

class SetupIssueRemoteResult implements ResultInterface
{
    public function __construct(
        public readonly string $remoteName,
        public readonly bool $alreadyExists,
        public readonly string $fetchOutput,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'remote_name' => $this->remoteName,
            'already_exists' => $this->alreadyExists,
            'fetch_output' => $this->fetchOutput,
        ];
    }
}
