<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Result\ResultInterface;

/**
 * A work item created on GitLab, or the payload a dry run would have sent.
 */
class WorkItemCreatedResult implements ResultInterface
{
    /**
     * @param string[] $labels
     */
    public function __construct(
        public readonly string $projectPath,
        public readonly string $title,
        public readonly string $description,
        public readonly array $labels,
        public readonly ?int $issueIid = null,
        public readonly ?string $webUrl = null,
    ) {
    }

    public function isDryRun(): bool
    {
        return $this->issueIid === null;
    }

    public function workItemUrl(): ?string
    {
        if ($this->webUrl !== null) {
            return $this->webUrl;
        }
        if ($this->issueIid === null) {
            return null;
        }
        return sprintf(
            'https://git.drupalcode.org/%s/-/work_items/%d',
            $this->projectPath,
            $this->issueIid,
        );
    }

    /**
     * The shorthand other issue commands accept, e.g. `tool#3583107`.
     */
    public function ref(): ?string
    {
        if ($this->issueIid === null) {
            return null;
        }
        return sprintf('%s#%d', $this->projectPath, $this->issueIid);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project_path' => $this->projectPath,
            'issue_iid' => $this->issueIid,
            'ref' => $this->ref(),
            'url' => $this->workItemUrl(),
            'title' => $this->title,
            'description' => $this->description,
            'labels' => $this->labels,
            'dry_run' => $this->isDryRun(),
        ];
    }
}
