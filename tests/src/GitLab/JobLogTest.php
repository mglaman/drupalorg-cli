<?php

namespace mglaman\DrupalOrg\Tests\GitLab;

use mglaman\DrupalOrg\GitLab\JobLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JobLog::class)]
class JobLogTest extends TestCase
{
    public function testStripsRunnerNoiseFromRawLog(): void
    {
        $raw = implode("\n", [
            "2026-09-04T11:35:49.840964Z 00O \e[0KRunning with gitlab-runner 19.3.1 (a16f5092)\e[0;m",
            "2026-09-04T11:35:49.841610Z 00O section_start:1788521749:prepare_executor\r\e[0K",
            "2026-09-04T11:35:49.841620Z 00O+\e[0K\e[36;1mPreparing the \"kubernetes\" executor\e[0;m\e[0;m",
            "2026-09-04T11:41:05.496963Z 01O ",
            "2026-09-04T11:41:05.497142Z 00O section_end:1788522065:upload_artifacts_on_failure\r\e[0K",
            "2026-09-04T11:41:06.018000Z 00O \e[31;1mERROR: Job failed: command terminated with exit code 1\e[0;m",
        ]);

        self::assertSame(implode("\n", [
            'Running with gitlab-runner 19.3.1 (a16f5092)',
            'Preparing the "kubernetes" executor',
            '',
            'ERROR: Job failed: command terminated with exit code 1',
        ]), JobLog::clean($raw));
    }

    public function testKeepsCollapsedSectionTitleOnSameLine(): void
    {
        $raw = "\e[0Ksection_start:1700000000:step_script[collapsed=true]\r\e[0K\e[32;1m\$ vendor/bin/phpunit\e[0;m";

        self::assertSame('$ vendor/bin/phpunit', JobLog::clean($raw));
    }

    public function testPlainTextIsUnchanged(): void
    {
        $plain = "PHPUnit 10.5\n\nFAILURES!\nTests: 3, Failures: 1.";

        self::assertSame($plain, JobLog::clean($plain));
    }
}
