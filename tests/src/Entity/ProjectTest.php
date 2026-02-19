<?php

namespace mglaman\DrupalOrg\Tests\Entity;

use mglaman\DrupalOrg\Entity\Project;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Project::class)]
class ProjectTest extends TestCase
{
    private static function fixture(): \stdClass
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/project_node.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function testFromStdClass(): void
    {
        $project = Project::fromStdClass(self::fixture());

        self::assertSame('2421989', $project->nid);
        self::assertSame('Address', $project->title);
        self::assertSame('address', $project->machineName);
    }

    public function testFromStdClassWithEmptyData(): void
    {
        $project = Project::fromStdClass(new \stdClass());

        self::assertSame('', $project->nid);
        self::assertSame('', $project->title);
        self::assertSame('', $project->machineName);
    }
}
