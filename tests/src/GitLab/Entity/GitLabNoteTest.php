<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\GitLab\Entity;

use mglaman\DrupalOrg\GitLab\Entity\GitLabNote;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GitLabNote::class)]
class GitLabNoteTest extends TestCase
{
    public function testDiffNoteKeepsPathAndNewLine(): void
    {
        $note = GitLabNote::fromStdClass((object) [
            'id' => 3625786,
            'type' => 'DiffNote',
            'body' => 'This needs a null check.',
            'author' => (object) ['username' => 'reviewer'],
            'created_at' => '2026-10-20T09:00:00Z',
            'system' => false,
            'position' => (object) [
                'old_path' => 'src/Old.php',
                'new_path' => 'src/New.php',
                'old_line' => null,
                'new_line' => 42,
            ],
        ]);

        self::assertSame('src/New.php', $note->path);
        self::assertSame(42, $note->line);
        self::assertSame(
            [
                'id' => 3625786,
                'body' => 'This needs a null check.',
                'author' => 'reviewer',
                'created_at' => '2026-10-20T09:00:00Z',
                'path' => 'src/New.php',
                'line' => 42,
            ],
            $note->jsonSerialize()
        );
    }

    public function testDiffNoteOnRemovedLineUsesOldLine(): void
    {
        $note = GitLabNote::fromStdClass((object) [
            'id' => 1,
            'position' => (object) ['old_path' => 'src/Gone.php', 'new_path' => 'src/Gone.php', 'old_line' => 7, 'new_line' => null],
        ]);

        self::assertSame('src/Gone.php', $note->path);
        self::assertSame(7, $note->line);
    }

    public function testRegularNoteHasNoPosition(): void
    {
        $note = GitLabNote::fromStdClass((object) [
            'id' => 2,
            'body' => 'Looks good.',
            'author' => (object) ['username' => 'reviewer'],
            'created_at' => '2026-10-20T09:00:00Z',
        ]);

        self::assertNull($note->path);
        self::assertNull($note->line);
        self::assertArrayNotHasKey('path', (array) $note->jsonSerialize());
    }

    public function testIsFromBot(): void
    {
        self::assertTrue(GitLabNote::fromStdClass((object) ['author' => (object) ['username' => 'drupalbot']])->isFromBot());
        self::assertFalse(GitLabNote::fromStdClass((object) ['author' => (object) ['username' => 'reviewer']])->isFromBot());
    }
}
