<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Result\ResultInterface;

class SlashCommandResult implements ResultInterface
{
    public function __construct(
        public readonly string $projectPath,
        public readonly int $issueIid,
        public readonly string $command,
        public readonly int $noteId,
    ) {
    }

    public function workItemUrl(): string
    {
        return sprintf(
            'https://git.drupalcode.org/%s/-/work_items/%d',
            $this->projectPath,
            $this->issueIid,
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project_path' => $this->projectPath,
            'issue_iid' => $this->issueIid,
            'command' => $this->command,
            'note_id' => $this->noteId,
            'url' => $this->workItemUrl(),
        ];
    }
}
