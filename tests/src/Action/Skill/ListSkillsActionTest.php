<?php

namespace mglaman\DrupalOrg\Tests\Action\Skill;

use mglaman\DrupalOrg\Action\Skill\ListSkillsAction;
use mglaman\DrupalOrg\Result\Skill\SkillItem;
use mglaman\DrupalOrg\Result\Skill\SkillListResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListSkillsAction::class)]
#[CoversClass(SkillListResult::class)]
#[CoversClass(SkillItem::class)]
class ListSkillsActionTest extends TestCase
{
    public function testListsBundledSkillsSortedByName(): void
    {
        $result = (new ListSkillsAction())();

        self::assertInstanceOf(SkillListResult::class, $result);
        self::assertNotEmpty($result->skills);

        $names = array_map(static fn(SkillItem $skill) => $skill->name, $result->skills);
        $sorted = $names;
        sort($sorted);
        self::assertSame($sorted, $names);
        self::assertContains('drupalorg-cli', $names);

        foreach ($result->skills as $skill) {
            self::assertNotSame('', $skill->description, "Skill {$skill->name} has no description");
            self::assertStringNotContainsString("\n", $skill->description);
            self::assertStringEndsWith('/' . $skill->name . '/SKILL.md', $skill->path);
            self::assertFileExists($skill->path);
        }
    }

    public function testFoldedDescriptionIsJoinedIntoOneLine(): void
    {
        $result = (new ListSkillsAction())();

        $cli = array_values(array_filter(
            $result->skills,
            static fn(SkillItem $skill) => $skill->name === 'drupalorg-cli'
        ));
        self::assertCount(1, $cli);
        self::assertStringStartsWith('CLI for Drupal.org issue lifecycle management.', $cli[0]->description);
    }

    public function testMissingRootReturnsEmptyList(): void
    {
        $result = (new ListSkillsAction(__DIR__ . '/does-not-exist'))();

        self::assertSame([], $result->skills);
    }

    public function testJsonSerialize(): void
    {
        $result = (new ListSkillsAction())();

        $decoded = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('skills', $decoded);
        self::assertSame(['name', 'description', 'path'], array_keys($decoded['skills'][0]));
    }
}
