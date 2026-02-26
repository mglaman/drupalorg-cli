<?php

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Result\ResultInterface;

class IssueForkResult implements ResultInterface
{
    /**
     * @param string[] $branches
     */
    public function __construct(
        public readonly string $remoteName,
        public readonly string $sshUrl,
        public readonly string $httpsUrl,
        public readonly string $gitLabProjectPath,
        public readonly array $branches,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'remote_name' => $this->remoteName,
            'ssh_url' => $this->sshUrl,
            'https_url' => $this->httpsUrl,
            'gitlab_project_path' => $this->gitLabProjectPath,
            'branches' => $this->branches,
        ];
    }
}
