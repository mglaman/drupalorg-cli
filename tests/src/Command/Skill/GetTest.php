<?php

namespace mglaman\DrupalOrg\Tests\Command\Skill;

use mglaman\DrupalOrgCli\Command\Skill\Get;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Get::class)]
class GetTest extends TestCase
{
    public function testClassExists(): void
    {
        $command = new Get();
        self::assertInstanceOf(Get::class, $command);
        self::assertSame('skill:get', $command->getName());
    }
}
