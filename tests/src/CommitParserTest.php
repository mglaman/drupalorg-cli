<?php

namespace mglaman\DrupalOrg\Tests;

use mglaman\DrupalOrg\CommitParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommitParser::class)]
class CommitParserTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function conventionalCommitProvider(): array
    {
        return [
            'fix maps to Bug' => ['fix: #123 correct the thing', 'Bug'],
            'feat maps to Feature' => ['feat: add a new thing', 'Feature'],
            'chore maps to Task' => ['chore: tidy up', 'Task'],
            'scope with spaces' => ['feat(CLI Tool): add a flag', 'Feature'],
            'scope with spaces and bang' => ['chore(Project management)!: drop support', 'Task'],
            'unmapped type' => ['docs: update readme', null],
            'not conventional' => ['Issue #123: fix the thing by user:', null],
        ];
    }

    #[DataProvider('conventionalCommitProvider')]
    public function testCategoryFromConventionalCommit(string $title, ?string $expected): void
    {
        self::assertSame($expected, CommitParser::categoryFromConventionalCommit($title));
    }
}
