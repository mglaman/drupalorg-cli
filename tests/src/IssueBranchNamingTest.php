<?php

namespace mglaman\DrupalOrg\Tests;

use mglaman\DrupalOrg\IssueBranchNaming;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IssueBranchNaming::class)]
class IssueBranchNamingTest extends TestCase
{
    public function testSlug(): void
    {
        self::assertSame('fix_js_on_add_form_a', IssueBranchNaming::slug('Fix JS on add form and remove jQuery dependency'));
        self::assertSame('schedule_transition', IssueBranchNaming::slug('[Schedule] transition'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function versions(): iterable
    {
        yield 'semver dev' => ['2.0.x-dev', '2.0.x'];
        yield 'semver release' => ['2.0.0-beta2', '2.0.x'];
        yield 'legacy dev' => ['8.x-1.x-dev', '8.x-1.x'];
    }

    #[DataProvider('versions')]
    public function testVersionBranch(string $version, string $expected): void
    {
        self::assertSame($expected, IssueBranchNaming::versionBranch($version));
    }
}
