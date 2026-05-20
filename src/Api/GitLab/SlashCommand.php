<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\GitLab;

/**
 * Builds the comment bodies the Drupal.org bot recognises on GitLab work items.
 *
 * See https://new.drupal.org/drupalorg/gitlab-custom-commands.
 */
final class SlashCommand
{
    public static function fork(): string
    {
        return '/do:fork';
    }

    public static function access(): string
    {
        return '/do:access';
    }

    /**
     * @param array<int, string> $users
     */
    public static function assign(array $users): string
    {
        return '/do:assign ' . self::formatUsers($users);
    }

    /**
     * @param array<int, string> $users
     */
    public static function unassign(array $users): string
    {
        return '/do:unassign ' . self::formatUsers($users);
    }

    /**
     * @param array<int, string> $users
     */
    public static function reassign(array $users): string
    {
        return '/do:reassign ' . self::formatUsers($users);
    }

    /**
     * @param array<int, string> $labels
     */
    public static function label(array $labels): string
    {
        return '/do:label ' . self::formatLabels($labels);
    }

    /**
     * @param array<int, string> $labels
     */
    public static function unlabel(array $labels): string
    {
        return '/do:unlabel ' . self::formatLabels($labels);
    }

    /**
     * @param array<int, string> $labels
     */
    public static function relabel(array $labels): string
    {
        return '/do:relabel ' . self::formatLabels($labels);
    }

    /**
     * @param array<int, string> $users
     */
    private static function formatUsers(array $users): string
    {
        if ($users === []) {
            throw new \InvalidArgumentException('At least one user is required.');
        }
        return implode(' ', array_map(self::formatUser(...), $users));
    }

    private static function formatUser(string $user): string
    {
        $user = ltrim(trim($user), '@');
        if ($user === '') {
            throw new \InvalidArgumentException('User name cannot be empty.');
        }
        return $user === 'me' ? 'me' : '@' . $user;
    }

    /**
     * @param array<int, string> $labels
     */
    private static function formatLabels(array $labels): string
    {
        if ($labels === []) {
            throw new \InvalidArgumentException('At least one label is required.');
        }
        return implode(' ', array_map(self::formatLabel(...), $labels));
    }

    private static function formatLabel(string $label): string
    {
        $label = ltrim(trim($label), '~');
        if ($label === '') {
            throw new \InvalidArgumentException('Label cannot be empty.');
        }
        return '~' . $label;
    }
}
