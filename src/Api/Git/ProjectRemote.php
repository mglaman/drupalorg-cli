<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Git;

use Symfony\Component\Process\Process;

/**
 * Reads the Drupal.org project machine name from a repository's git remotes.
 *
 * Recognizes the canonical project clone URLs:
 *   git@git.drupal.org:project/{name}.git
 *   https://git.drupalcode.org/project/{name}.git
 *
 * Issue fork remotes (issue/{name}-{nid}) and personal forks are ignored.
 */
final class ProjectRemote
{
    private const URL_PATTERN = '~(?:git\.drupal\.org|git\.drupalcode\.org)[:/]project/([A-Za-z0-9_-]+?)(?:\.git)?/?$~';

    public static function machineNameFromUrl(string $url): ?string
    {
        if (preg_match(self::URL_PATTERN, trim($url), $matches) !== 1) {
            return null;
        }
        return $matches[1];
    }

    /**
     * @param array<string, string> $remoteUrls
     *   Fetch URLs keyed by remote name. "origin" wins when it matches.
     */
    public static function machineNameFromRemotes(array $remoteUrls): ?string
    {
        if (isset($remoteUrls['origin'])) {
            $fromOrigin = self::machineNameFromUrl($remoteUrls['origin']);
            if ($fromOrigin !== null) {
                return $fromOrigin;
            }
        }
        foreach ($remoteUrls as $url) {
            $machineName = self::machineNameFromUrl($url);
            if ($machineName !== null) {
                return $machineName;
            }
        }
        return null;
    }

    /**
     * Detects the project from the remotes of the repository at $cwd.
     * Returns null outside a git repository or when no remote matches.
     */
    public static function detect(?string $cwd = null): ?string
    {
        $process = new Process(['git', 'remote', '-v'], $cwd);
        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }
        return self::machineNameFromRemotes(self::parseRemoteList($process->getOutput()));
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
