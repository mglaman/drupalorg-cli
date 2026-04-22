<?php

namespace mglaman\DrupalOrg\Tests\Entity;

use mglaman\DrupalOrg\Entity\IssueFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IssueFile::class)]
class IssueFileTest extends TestCase
{
    private static function fixture(): \stdClass
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/issue_file.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function testFromStdClass(): void
    {
        $issueFile = IssueFile::fromStdClass(self::fixture());

        self::assertTrue($issueFile->display);
        self::assertSame('3786488', $issueFile->fileId);
        self::assertSame(5, $issueFile->cid);
    }

    public function testFromStdClassWithEmptyData(): void
    {
        $issueFile = IssueFile::fromStdClass(new \stdClass());

        self::assertFalse($issueFile->display);
        self::assertSame('', $issueFile->fileId);
        self::assertSame(0, $issueFile->cid);
    }
}
