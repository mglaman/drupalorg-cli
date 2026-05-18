<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\GitLab;

/**
 * Parses a GitLab work item or issue URL into project path + issue ID.
 *
 * Supports:
 *   https://git.drupalcode.org/{path}/-/work_items/{id}
 *   https://git.drupalcode.org/{path}/-/issues/{id}
 *
 * On drupalcode.org the issue iid equals the Drupal.org NID.
 */
class WorkItemRef
{
    public function __construct(
        public string $projectPath,
        public int $issueId,
    ) {
    }

    public static function tryParse(string $input): ?self
    {
        if (!str_starts_with($input, 'https://git.drupalcode.org/')) {
            return null;
        }
        $pattern = '#^https://git\.drupalcode\.org/(.+)/-/(?:work_items|issues)/(\d+)#';
        if (!preg_match($pattern, $input, $matches)) {
            return null;
        }
        return new self(
            projectPath: $matches[1],
            issueId: (int) $matches[2],
        );
    }
}
