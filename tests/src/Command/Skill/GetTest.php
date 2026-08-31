<?php

namespace mglaman\DrupalOrg\Tests\Command\Skill;

use mglaman\DrupalOrgCli\Command\Skill\Get;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(Get::class)]
class GetTest extends TestCase
{
    public function testClassExists(): void
    {
        $command = new Get();
        self::assertInstanceOf(Get::class, $command);
        self::assertSame('skill:get', $command->getName());
    }

    public function testNoNameListsSkillsAsTable(): void
    {
        $tester = new CommandTester(new Get());
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('| Skill', $display);
        self::assertStringContainsString('| Description', $display);
        self::assertStringContainsString('drupalorg-cli', $display);
        self::assertStringContainsString('drupalorg-work-on-issue', $display);
        self::assertStringContainsString('Run: drupalorg skill:get <name>', $display);
    }

    public function testNoNameListsSkillsAsJson(): void
    {
        $tester = new CommandTester(new Get());
        $exitCode = $tester->execute(['--format' => 'json']);

        self::assertSame(0, $exitCode);
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $names = array_column($decoded['skills'], 'name');
        self::assertContains('drupalorg-cli', $names);
    }

    public function testNoNameListsSkillsAsLlm(): void
    {
        $tester = new CommandTester(new Get());
        $exitCode = $tester->execute(['--format' => 'llm']);

        self::assertSame(0, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringStartsWith('<skills>', $display);
        self::assertStringContainsString('<name>drupalorg-cli</name>', $display);
    }

    public function testNamedSkillOutputsContent(): void
    {
        $tester = new CommandTester(new Get());
        $exitCode = $tester->execute(['name' => 'drupalorg-cli']);

        self::assertSame(0, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringStartsWith("---\nname: drupalorg-cli", $display);
        self::assertStringNotContainsString('Run: drupalorg skill:get <name>', $display);
    }

    public function testUnknownSkillFailsAndListsNames(): void
    {
        $tester = new CommandTester(new Get());
        $exitCode = $tester->execute(['name' => 'does-not-exist']);

        self::assertSame(1, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Skill not found: does-not-exist', $display);
        self::assertStringContainsString('Available skills: drupalorg-cli', $display);
    }
}
