<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg;

/**
 * Branch naming rules shared by Drupal.org issues and GitLab work items.
 */
final class IssueBranchNaming
{
    /**
     * Lowercase, underscore-separated, at most 20 characters of the title.
     */
    public static function slug(string $title): string
    {
        $slug = (string) preg_replace('/[^a-zA-Z0-9]+/', '_', $title);
        $slug = strtolower(substr($slug, 0, 20));
        return (string) preg_replace('/(^_|_$)/', '', $slug);
    }

    /**
     * The development branch for an issue version such as "2.0.x-dev",
     * "2.0.0-beta2" or "8.x-1.x-dev".
     */
    public static function versionBranch(string $version): string
    {
        if (preg_match('/^(\d+\.\d+)\./', $version, $matches)) {
            return $matches[1] . '.x';
        }
        return substr($version, 0, 6) . 'x';
    }
}
