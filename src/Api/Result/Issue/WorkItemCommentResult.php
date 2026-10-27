<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Result\ResultInterface;

class WorkItemCommentResult implements ResultInterface
{
    public function __construct(
        public readonly string $projectPath,
        public readonly int $issueIid,
        public readonly int $noteId,
    ) {
    }

    public function noteUrl(): string
    {
        return sprintf(
            'https://git.drupalcode.org/%s/-/work_items/%d#note_%d',
            $this->projectPath,
            $this->issueIid,
            $this->noteId,
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project_path' => $this->projectPath,
            'issue_iid' => $this->issueIid,
            'note_id' => $this->noteId,
            'url' => $this->noteUrl(),
        ];
    }
}
