<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Result\Project;

use mglaman\DrupalOrg\Result\ResultInterface;

class ProjectContainsResult implements ResultInterface
{
    /**
     * @param string[] $branches
     * @param string[] $tags
     *   Ordered oldest release first, so the first tag shipped the commit.
     */
    public function __construct(
        public readonly string $projectName,
        public readonly string $sha,
        public readonly string $title,
        public readonly string $committedDate,
        public readonly string $webUrl,
        public readonly array $branches,
        public readonly array $tags,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project' => $this->projectName,
            'sha' => $this->sha,
            'title' => $this->title,
            'committed_date' => $this->committedDate,
            'web_url' => $this->webUrl,
            'branches' => $this->branches,
            'tags' => $this->tags,
        ];
    }
}
