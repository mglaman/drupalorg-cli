<?php

namespace mglaman\DrupalOrg\Tests\Entity;

use mglaman\DrupalOrg\Entity\Release;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Release::class)]
class ReleaseTest extends TestCase
{
    private static function fixture(): \stdClass
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/release_node.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function testFromStdClass(): void
    {
        $release = Release::fromStdClass(self::fixture());

        self::assertSame('3571658', $release->nid);
        self::assertSame('10.6.3', $release->fieldReleaseVersion);
        self::assertNull($release->fieldReleaseVersionExtra);
        self::assertStringContainsString('Drupal 10', $release->fieldReleaseShortDescription);
        self::assertSame(1770279556, $release->created);
        self::assertSame('3060', $release->fieldReleaseProject);
        self::assertNotEmpty($release->bodyValue);
    }

    public function testFromStdClassWithEmptyData(): void
    {
        $release = Release::fromStdClass(new \stdClass());

        self::assertSame('', $release->nid);
        self::assertNull($release->fieldReleaseVersionExtra);
        self::assertSame('', $release->fieldReleaseProject);
        self::assertNull($release->bodyValue);
    }
}
