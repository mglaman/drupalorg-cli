<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg;

/**
 * The Drupal.org project a git remote URL points at.
 */
final class ProjectRemote
{
    /**
     * Matches SSH (git@host:path), ssh://, and https:// remotes on either
     * Drupal.org git host, with or without a trailing ".git".
     */
    private const PATTERN = '#^(?:(?:ssh|https?)://)?(?:[^@/\s]+@)?git\.(?:drupal|drupalcode)\.org[:/]project/(?<name>[A-Za-z0-9_-]+)(?:\.git)?/?$#';

    public function __construct(public readonly string $machineName)
    {
    }

    public static function tryParse(string $remoteUrl): ?self
    {
        $matches = [];
        if (preg_match(self::PATTERN, trim($remoteUrl), $matches) !== 1) {
            return null;
        }
        return new self($matches['name']);
    }
}
