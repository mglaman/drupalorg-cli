<?php

namespace mglaman\DrupalOrg\Enum;

enum ProjectIssueCategory: string
{
    case Bug = 'bug';
    case Task = 'task';
    case Feature = 'feature';
    case Support = 'support';
    case Plan = 'plan';

    public function categoryId(): int
    {
        return match ($this) {
            ProjectIssueCategory::Bug => 1,
            ProjectIssueCategory::Task => 2,
            ProjectIssueCategory::Feature => 3,
            ProjectIssueCategory::Support => 4,
            ProjectIssueCategory::Plan => 5,
        };
    }
}
