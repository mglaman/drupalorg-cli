<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\GitLab;

/**
 * Value object representing a direct merge request reference.
 *
 * Supports formats like "project/canvas!708", "project/canvas",
 * or full GitLab URLs.
 */
final class MergeRequestRef
{
    public function __construct(
        public readonly string $projectPath,
        public readonly ?int $mrIid = null,
    ) {
    }

    /**
     * Try to parse a string into a MergeRequestRef.
     *
     * Returns null if the input is a pure numeric NID or unrecognized format.
     */
    public static function tryParse(string $input): ?self
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        // Pure numeric string → NID, fall through.
        if (ctype_digit($input)) {
            return null;
        }

        // Full GitLab URL.
        if (str_starts_with($input, 'https://git.drupalcode.org/')) {
            return self::parseUrl($input);
        }

        // project/name!iid format.
        if (preg_match('#^([a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+)!(\d+)$#', $input, $matches)) {
            return new self($matches[1], (int) $matches[2]);
        }

        // project/name format (no IID).
        if (preg_match('#^[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+$#', $input)) {
            return new self($input);
        }

        return null;
    }

    private static function parseUrl(string $url): ?self
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === false) {
            return null;
        }
        $path = ltrim($path, '/');

        // Match: project/name/-/merge_requests/123
        if (preg_match('#^([a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+)/-/merge_requests/(\d+)#', $path, $matches)) {
            return new self($matches[1], (int) $matches[2]);
        }

        // Match: project/name (with possible trailing segments but no merge_requests)
        if (preg_match('#^([a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+)(?:/.*)?$#', $path, $matches)) {
            return new self($matches[1]);
        }

        return null;
    }
}
