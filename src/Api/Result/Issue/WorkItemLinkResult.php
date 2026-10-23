<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Result\ResultInterface;

class WorkItemLinkResult implements ResultInterface
{
    public function __construct(
        public readonly string $sourceProjectPath,
        public readonly int $sourceIid,
        public readonly string $linkType,
        public readonly string $targetProjectPath,
        public readonly int $targetIid,
    ) {
    }

    public function sourceUrl(): string
    {
        return self::workItemUrl($this->sourceProjectPath, $this->sourceIid);
    }

    public function targetUrl(): string
    {
        return self::workItemUrl($this->targetProjectPath, $this->targetIid);
    }

    private static function workItemUrl(string $projectPath, int $iid): string
    {
        return sprintf('https://git.drupalcode.org/%s/-/work_items/%d', $projectPath, $iid);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'source' => [
                'project_path' => $this->sourceProjectPath,
                'issue_iid' => $this->sourceIid,
                'url' => $this->sourceUrl(),
            ],
            'link_type' => $this->linkType,
            'target' => [
                'project_path' => $this->targetProjectPath,
                'issue_iid' => $this->targetIid,
                'url' => $this->targetUrl(),
            ],
        ];
    }
}
