<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Result\ResultInterface;

/**
 * A project's issue description template.
 */
class IssueTemplateResult implements ResultInterface
{
    /**
     * @param string|null $name
     *   The template's name, or null when no name was asked for and the
     *   project has no Default template.
     * @param string[] $available
     *   Every issue template the project has.
     */
    public function __construct(
        public readonly string $projectPath,
        public readonly ?string $name,
        public readonly string $content,
        public readonly array $available,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project_path' => $this->projectPath,
            'name' => $this->name,
            'content' => $this->content,
            'available' => $this->available,
        ];
    }
}
