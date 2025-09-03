<?php declare(strict_types=1);

namespace mglaman\DrupalOrgCli;

final class CommitParser {

    public static function extractUsernames(\stdClass $commit, $sort = false): array {
        $from_title = self::extractUsernamesFromString($commit->title);
        $from_message = self::extractUsernamesFromString($commit->message);
        $usernames = array_merge($from_title, $from_message);

        $usernames = array_values(array_unique($usernames));
        if ($sort) {
            sort($usernames);
        }

        return $usernames;
    }

    private static function extractUsernamesFromString(string $message): array {
        $usernames = [];
        // The classic "by" line in a commit title.
        $matches = [];
        if (preg_match('/by ([^:]+):/S', $message, $matches) === 1) {
            foreach (explode(',', $matches[1]) as $user) {
                $usernames[] = trim($user);
            }
        }

        if (preg_match_all('/^(?:By|Authored-by|Co-authored-by): ([^<]+)(?: <[^>]+>)?$/mi', $message, $matches)) {
            foreach ($matches[1] as $user) {
                $usernames[] = trim($user);
            }
        }
        return $usernames;
    }

    public static function getNid(string $title): ?string {
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
