<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\GitLab;

use mglaman\DrupalOrg\GitLab\WorkItemRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkItemRef::class)]
class WorkItemRefTest extends TestCase
{
    #[DataProvider('parseProvider')]
    public function testTryParse(string $input, ?string $expectedPath, ?int $expectedId, ?string $expectedMachineName): void
    {
        $ref = WorkItemRef::tryParse($input);

        if ($expectedPath === null) {
            self::assertNull($ref, "Expected null for input: $input");
            return;
        }

        self::assertNotNull($ref, "Expected non-null ref for input: $input");
        self::assertSame($expectedPath, $ref->projectPath);
        self::assertSame($expectedId, $ref->issueId);
        self::assertSame($expectedMachineName, $ref->projectMachineName());
    }

    /**
     * @return array<string, array{string, ?string, ?int, ?string}>
     */
    public static function parseProvider(): array
    {
        return [
            'shorthand' => ['campaign#3615648', 'project/campaign', 3615648, 'campaign'],
            'project path' => ['project/campaign#3615648', 'project/campaign', 3615648, 'campaign'],
            'work item URL' => [
                'https://git.drupalcode.org/project/campaign/-/work_items/3615635',
                'project/campaign',
                3615635,
                'campaign',
            ],
            'issue URL' => [
                'https://git.drupalcode.org/project/canvas/-/issues/3591806',
                'project/canvas',
                3591806,
                'canvas',
            ],
            'bare nid' => ['3615648', null, null, null],
            'empty' => ['', null, null, null],
            'unknown URL' => ['https://git.drupalcode.org/project/campaign', null, null, null],
        ];
    }
}
