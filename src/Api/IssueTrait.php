<?php

namespace mglaman\DrupalOrg;

trait IssueTrait
{
    /**
     * Produces the label for a given category id.
     *
     * @param int $value
     *   Category identifier.
     *
     * @return string
     *   The label if known, or the casted string value if not.
     */
    public function getIssueCategoryLabel(int $value): string
    {
        switch ($value) {
            case 1:
                return 'Bug report';
            case 2:
                return 'Task';
            case 3:
                return 'Feature request';
            case 4:
                return 'Support request';
            case 5:
                return 'Plan';
            default:
                return (string)$value;
        }
    }

    /**
     * Produces the label for a given priority id.
     *
     * @param int $value
     *   Priority identifier.
     *
     * @return string
     *   The label if known, or the casted string value if not.
     */
    public function getIssuePriorityLabel(int $value): string
    {
        switch ($value) {
            case 100:
                return 'Minor';
            case 200:
                return 'Normal';
            case 300:
                return 'Major';
            case 400:
                return 'Critical';
            default:
                return (string)$value;
        }
    }

    /**
     * Produces the label for a given status id.
     *
     * @param int $value
     *   Status identifier.
     *
     * @return string
     *   The label if known, or the casted string value if not.
     */
    public function getIssueStatusLabel(int $value): string
    {
        switch ($value) {
            case 1:
                return 'Active';
            case 2:
                return 'Fixed';
            case 3:
                return 'Closed (duplicate)';
            case 4:
                return 'Postponed';
            case 5:
                return "Closed (won't fix)";
            case 6:
                return 'Closed (works as designed)';
            case 7:
                return 'Closed (fixed)';
            case 15:
                return 'Patch (to be ported)';
            case 17:
                return 'Closed (outdated)';
            case 18:
                return 'Closed (cannot reproduce)';
            case 13:
                return 'Needs Work';
            case 8:
                return 'Needs Review';
            case 16:
                return 'Postponed [NMI]';
            case 14:
                return 'RTBC';
            default:
                return (string)$value;
        }
    }
}
