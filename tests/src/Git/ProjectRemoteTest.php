<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Git;

use mglaman\DrupalOrg\Git\ProjectRemote;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectRemote::class)]
class ProjectRemoteTest extends TestCase
{
    #[DataProvider('urlProvider')]
    public function testMachineNameFromUrl(string $url, ?string $expected): void
    {
        self::assertSame($expected, ProjectRemote::machineNameFromUrl($url));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function urlProvider(): array
    {
        return [
            'ssh' => ['git@git.drupal.org:project/campaign.git', 'campaign'],
            'https' => ['https://git.drupalcode.org/project/campaign.git', 'campaign'],
            'https without suffix' => ['https://git.drupalcode.org/project/drupal', 'drupal'],
            'ssh scheme' => ['ssh://git@git.drupal.org/project/ai_context.git', 'ai_context'],
            'issue fork' => ['git@git.drupal.org:issue/campaign-3615648.git', null],
            'personal fork' => ['git@git.drupal.org:mglaman/campaign.git', null],
            'github mirror' => ['https://github.com/drupal/drupal.git', null],
            'empty' => ['', null],
        ];
    }

    public function testOriginWinsOverOtherRemotes(): void
    {
        $machineName = ProjectRemote::machineNameFromRemotes([
            'upstream' => 'git@git.drupal.org:project/drupal.git',
            'origin' => 'https://git.drupalcode.org/project/campaign.git',
        ]);

        self::assertSame('campaign', $machineName);
    }

    public function testFallsBackToAnyProjectRemote(): void
    {
        $machineName = ProjectRemote::machineNameFromRemotes([
            'origin' => 'git@github.com:mglaman/campaign.git',
            'campaign-3615648' => 'git@git.drupal.org:issue/campaign-3615648.git',
            'drupal' => 'git@git.drupal.org:project/campaign.git',
        ]);

        self::assertSame('campaign', $machineName);
    }

    public function testNoProjectRemote(): void
    {
        self::assertNull(ProjectRemote::machineNameFromRemotes([]));
        self::assertNull(ProjectRemote::machineNameFromRemotes([
            'origin' => 'git@git.drupal.org:issue/campaign-3615648.git',
        ]));
    }

    public function testDetectOutsideRepository(): void
    {
        self::assertNull(ProjectRemote::detect(sys_get_temp_dir()));
    }
}
