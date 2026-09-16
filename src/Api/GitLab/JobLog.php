<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\GitLab;

/**
 * Turns a raw GitLab job log into plain terminal output.
 *
 * Raw logs carry a runner timestamp prefix on every line, ANSI color codes,
 * and section_start/section_end markers that the GitLab UI folds. None of
 * that helps a person or an agent read a failure.
 */
final class JobLog
{
    private const TIMESTAMP_PREFIX = '/^\d{4}-\d{2}-\d{2}T[\d:.]+Z \d+[EO][ +]/';
    private const ANSI_ESCAPE = '/\x1b\[[0-9;]*[A-Za-z]/';
    private const SECTION_MARKER = '/section_(?:start|end):\d+:[^\s\r]*\r?/';

    public static function clean(string $raw): string
    {
        $lines = [];
        foreach (explode("\n", $raw) as $line) {
            $line = (string) preg_replace(self::TIMESTAMP_PREFIX, '', $line);
            $line = (string) preg_replace(self::ANSI_ESCAPE, '', $line);
            $withoutMarkers = (string) preg_replace(self::SECTION_MARKER, '', $line);
            if ($withoutMarkers !== $line && trim($withoutMarkers) === '') {
                continue;
            }
            $lines[] = rtrim($withoutMarkers, "\r");
        }
        return implode("\n", $lines);
    }
}
