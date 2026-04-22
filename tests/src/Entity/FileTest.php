<?php

namespace mglaman\DrupalOrg\Tests\Entity;

use mglaman\DrupalOrg\Entity\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(File::class)]
class FileTest extends TestCase
{
    private static function fixture(): \stdClass
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/file.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function testFromStdClass(): void
    {
        $file = File::fromStdClass(self::fixture());

        self::assertSame('3786488', $file->fid);
        self::assertSame('simplenews_linkchecker-7.x-1.x-dev.zip', $file->name);
        self::assertStringContainsString('drupal.org/files', $file->url);
    }

    public function testFromStdClassWithEmptyData(): void
    {
        $file = File::fromStdClass(new \stdClass());

        self::assertSame('', $file->fid);
        self::assertSame('', $file->name);
        self::assertSame('', $file->url);
    }
}
