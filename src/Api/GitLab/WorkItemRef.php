<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\GitLab;

/**
 * A reference to a GitLab work item / issue.
 *
 * Supports:
 *   https://git.drupalcode.org/{path}/-/work_items/{id}
 *   https://git.drupalcode.org/{path}/-/issues/{id}
 *   project/ai_context#3586157
 *   ai_context#3586157   (shorthand; "project/" prefix assumed)
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
        $input = trim($input);

        if ($input === '' || ctype_digit($input)) {
            return null;
        }

        // Full GitLab URL.
        if (str_starts_with($input, 'https://git.drupalcode.org/')) {
            $pattern = '#^https://git\.drupalcode\.org/(.+)/-/(?:work_items|issues)/(\d+)#';
            if (preg_match($pattern, $input, $matches)) {
                return new self(projectPath: $matches[1], issueId: (int) $matches[2]);
            }
            return null;
        }

        // project/name#123
        if (preg_match('~^([a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+)#(\d+)$~', $input, $matches)) {
            return new self(projectPath: $matches[1], issueId: (int) $matches[2]);
        }

        // name#123  (shorthand; "project/" assumed)
        if (preg_match('~^([a-zA-Z0-9_-]+)#(\d+)$~', $input, $matches)) {
            return new self(projectPath: 'project/' . $matches[1], issueId: (int) $matches[2]);
        }

        return null;
    }
}
