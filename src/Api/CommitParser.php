<?php

namespace mglaman\DrupalOrg;

final class CommitParser
{
    public static function extractUsernames(\stdClass $commit, bool $sort = false): array
    {
        $from_title = self::extractUsernamesFromString($commit->title);
        $from_message = self::extractUsernamesFromString($commit->message);
        $usernames = array_merge($from_title, $from_message);

        $usernames = array_values(array_unique($usernames));
        if ($sort) {
            sort($usernames);
        }

        return $usernames;
    }

    private static function extractUsernamesFromString(string $message): array
    {
        $usernames = [];
        // The classic "by" line in a commit title.
        $matches = [];
        if (preg_match('/\bby ([^:\n]+):/S', $message, $matches) === 1) {
            foreach (explode(',', $matches[1]) as $user) {
                $usernames[] = ltrim(trim($user), '@');
            }
        }

        // Split message into lines and check each for username patterns.
        foreach (explode("\n", $message) as $line) {
            $line = trim($line);
            if (preg_match('/^(?:By|Authored-by|Co-authored-by):\s*([^<]+)(?:\s*<[^>]+>)?$/i', $line, $matches)) {
                $usernames[] = ltrim(trim($matches[1]), '@');
            }
        }
        return $usernames;
    }

    public static function getNid(string $title): ?string
    {
        $matches = [];
        // Drupal.org commits should have "Issue #{nid}".
        if (preg_match('/#(\d+)/S', $title, $matches) === 1) {
            return $matches[1];
        }
        // But maybe they forgot the leading "#" on the issue ID.
        if (preg_match('/([0-9]{4,})/S', $title, $matches) === 1) {
            return $matches[1];
        }
        return null;
    }
}
