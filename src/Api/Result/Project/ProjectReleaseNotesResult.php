<?php

namespace mglaman\DrupalOrg\Result\Project;

use mglaman\DrupalOrg\Result\ResultInterface;

class ProjectReleaseNotesResult implements ResultInterface
{
    public function __construct(
        public readonly string $projectName,
        public readonly string $version,
        public readonly string $body,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project_name' => $this->projectName,
            'version' => $this->version,
            'body' => $this->body,
        ];
    }
}
