<?php

namespace mglaman\DrupalOrg\Tests\Entity;

use mglaman\DrupalOrg\Entity\ChangeRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeRecord::class)]
class ChangeRecordTest extends TestCase
{
    private static function fixture(): \stdClass
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/change_record.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function testFromStdClass(): void
    {
        $record = ChangeRecord::fromStdClass(self::fixture());

        self::assertSame('Some API changed in this release', $record->title);
        self::assertSame('https://www.drupal.org/node/1234567', $record->url);
    }

    public function testFromStdClassWithEmptyData(): void
    {
        $record = ChangeRecord::fromStdClass(new \stdClass());

        self::assertSame('', $record->title);
        self::assertSame('', $record->url);
    }
}
