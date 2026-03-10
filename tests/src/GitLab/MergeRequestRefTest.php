<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\GitLab;

use mglaman\DrupalOrg\GitLab\MergeRequestRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MergeRequestRef::class)]
class MergeRequestRefTest extends TestCase
{
    #[DataProvider('parseProvider')]
    public function testTryParse(string $input, ?string $expectedPath, ?int $expectedIid): void
    {
        $ref = MergeRequestRef::tryParse($input);

        if ($expectedPath === null) {
            self::assertNull($ref, "Expected null for input: $input");
            return;
        }

        self::assertNotNull($ref, "Expected non-null ref for input: $input");
        self::assertSame($expectedPath, $ref->projectPath);
        self::assertSame($expectedIid, $ref->mrIid);
    }

    /**
     * @return array<string, array{string, ?string, ?int}>
     */
    public static function parseProvider(): array
    {
        return [
            'project-path with IID' => [
                'project/canvas!708',
                'project/canvas',
                708,
            ],
            'project-path without IID' => [
                'project/canvas',
                'project/canvas',
                null,
            ],
            'full URL with MR IID' => [
                'https://git.drupalcode.org/project/canvas/-/merge_requests/708',
                'project/canvas',
                708,
            ],
            'full URL without MR' => [
                'https://git.drupalcode.org/project/canvas',
                'project/canvas',
                null,
            ],
            'full URL with trailing slash' => [
                'https://git.drupalcode.org/project/drupal/',
                'project/drupal',
                null,
            ],
            'numeric NID falls through' => [
                '1234567',
                null,
                null,
            ],
            'random string falls through' => [
                'hello-world',
                null,
                null,
            ],
            'empty string falls through' => [
                '',
                null,
                null,
            ],
            'project with hyphens and underscores' => [
                'project/my_cool-module!42',
                'project/my_cool-module',
                42,
            ],
        ];
    }
}
