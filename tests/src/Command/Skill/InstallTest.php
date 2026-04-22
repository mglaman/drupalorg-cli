<?php

namespace mglaman\DrupalOrg\Tests\Command\Skill;

use mglaman\DrupalOrgCli\Command\Skill\Install;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Install::class)]
class InstallTest extends TestCase
{
    public function testClassExists(): void
    {
        $command = new Install();
        self::assertInstanceOf(Install::class, $command);
        self::assertSame('skill:install', $command->getName());
    }
}
