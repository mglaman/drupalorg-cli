<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\GitLab;

use mglaman\DrupalOrg\GitLab\SlashCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlashCommand::class)]
class SlashCommandTest extends TestCase
{
    public function testFork(): void
    {
        self::assertSame('/do:fork', SlashCommand::fork());
    }

    public function testAccess(): void
    {
        self::assertSame('/do:access', SlashCommand::access());
    }

    public function testAssignMe(): void
    {
        self::assertSame('/do:assign me', SlashCommand::assign(['me']));
    }

    public function testAssignSingleUser(): void
    {
        self::assertSame('/do:assign @johndoe', SlashCommand::assign(['johndoe']));
    }

    public function testAssignStripsLeadingAt(): void
    {
        self::assertSame('/do:assign @johndoe', SlashCommand::assign(['@johndoe']));
    }

    public function testAssignMultipleUsers(): void
    {
        self::assertSame(
            '/do:assign @alice @bob me',
            SlashCommand::assign(['alice', '@bob', 'me']),
        );
    }

    public function testUnassign(): void
    {
        self::assertSame('/do:unassign me', SlashCommand::unassign(['me']));
    }

    public function testReassign(): void
    {
        self::assertSame('/do:reassign @alice', SlashCommand::reassign(['alice']));
    }

    public function testLabelSingle(): void
    {
        self::assertSame('/do:label ~state::needsReview', SlashCommand::label(['state::needsReview']));
    }

    public function testLabelStripsLeadingTilde(): void
    {
        self::assertSame('/do:label ~state::rtbc', SlashCommand::label(['~state::rtbc']));
    }

    public function testLabelMultiple(): void
    {
        self::assertSame(
            '/do:label ~state::needsReview ~priority::critical',
            SlashCommand::label(['state::needsReview', '~priority::critical']),
        );
    }

    public function testUnlabel(): void
    {
        self::assertSame('/do:unlabel ~state::needsWork', SlashCommand::unlabel(['state::needsWork']));
    }

    public function testRelabel(): void
    {
        self::assertSame('/do:relabel ~state::rtbc', SlashCommand::relabel(['state::rtbc']));
    }

    public function testAssignRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SlashCommand::assign([]);
    }

    public function testLabelRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SlashCommand::label([]);
    }

    public function testAssignRejectsBlankUser(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SlashCommand::assign(['@']);
    }

    public function testLabelRejectsBlankLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SlashCommand::label(['~']);
    }
}
