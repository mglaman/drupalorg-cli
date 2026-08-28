<?php

namespace mglaman\DrupalOrg\Tests;

use mglaman\DrupalOrg\ProjectRemote;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectRemote::class)]
class ProjectRemoteTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function remoteUrlProvider(): array
    {
        return [
            'ssh scp-style' => ['git@git.drupal.org:project/json_form_widget.git', 'json_form_widget'],
            'ssh scp-style on drupalcode' => ['git@git.drupalcode.org:project/json_form_widget.git', 'json_form_widget'],
            'https' => ['https://git.drupalcode.org/project/json_form_widget.git', 'json_form_widget'],
            'https without .git' => ['https://git.drupalcode.org/project/json_form_widget', 'json_form_widget'],
            'ssh scheme' => ['ssh://git@git.drupal.org/project/json_form_widget.git', 'json_form_widget'],
            'trailing newline from git output' => ["git@git.drupal.org:project/json_form_widget.git\n", 'json_form_widget'],
            'github remote' => ['git@github.com:mglaman/drupalorg-cli.git', null],
            'issue fork' => ['git@git.drupal.org:issue/json_form_widget-3000000.git', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('remoteUrlProvider')]
    public function testTryParse(string $remoteUrl, ?string $expected): void
    {
        self::assertSame($expected, ProjectRemote::tryParse($remoteUrl)?->machineName);
    }
}
