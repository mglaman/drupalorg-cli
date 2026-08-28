<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg;

use Symfony\Component\Process\Process;

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

    /**
     * Picks the project remote from a repository's remotes. "origin" wins
     * when it matches; issue forks and personal forks never match.
     *
     * @param array<string, string> $remoteUrls
     *   Fetch URLs keyed by remote name.
     */
    public static function fromRemotes(array $remoteUrls): ?self
    {
        if (isset($remoteUrls['origin'])) {
            $fromOrigin = self::tryParse($remoteUrls['origin']);
            if ($fromOrigin !== null) {
                return $fromOrigin;
            }
        }
        foreach ($remoteUrls as $url) {
            $remote = self::tryParse($url);
            if ($remote !== null) {
                return $remote;
            }
        }
        return null;
    }

    /**
     * Detects the project from the remotes of the repository at $cwd.
     * Returns null outside a git repository or when no remote matches.
     */
    public static function detect(?string $cwd = null): ?self
    {
        $process = new Process(['git', 'remote', '-v'], $cwd);
        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }
        return self::fromRemotes(self::parseRemoteList($process->getOutput()));
    }

    /**
     * @return array<string, string>
     */
    private static function parseRemoteList(string $output): array
    {
        $remoteUrls = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^(\S+)\s+(\S+)\s+\(fetch\)$/', trim($line), $matches) === 1) {
                $remoteUrls[$matches[1]] = $matches[2];
            }
        }
        return $remoteUrls;
    }
}
