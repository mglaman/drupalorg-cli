<?php

namespace mglaman\DrupalOrg\Tests\Entity;

use mglaman\DrupalOrg\Entity\PiftJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PiftJob::class)]
class PiftJobTest extends TestCase
{
    private static function fixture(): \stdClass
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/pift_job.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function testFromStdClass(): void
    {
        $job = PiftJob::fromStdClass(self::fixture());

        self::assertSame('12345', $job->jobId);
        self::assertSame('3786488', $job->fileId);
        self::assertSame('3383637', $job->issueNid);
        self::assertSame('complete', $job->status);
        self::assertSame('pass', $job->result);
        self::assertSame(1693200000, $job->updated);
        self::assertStringContainsString('drupalci', $job->ciUrl);
    }

    public function testFromStdClassWithEmptyData(): void
    {
        $job = PiftJob::fromStdClass(new \stdClass());

        self::assertSame('', $job->jobId);
        self::assertSame('', $job->fileId);
        self::assertSame('', $job->issueNid);
        self::assertSame('', $job->status);
        self::assertSame('', $job->result);
        self::assertSame('', $job->message);
        self::assertSame(0, $job->updated);
        self::assertSame('', $job->ciUrl);
    }
}
