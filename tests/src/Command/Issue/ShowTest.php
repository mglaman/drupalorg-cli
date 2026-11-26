<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Command\Issue;

use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrgCli\Command\Issue\Show;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(Show::class)]
class ShowTest extends TestCase
{
    private const MARKUP = 'Use <comment>, <fg=red>x</>, and Drupal\<Widget>';

    public function testWorkItemTextIsNotFormatted(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssue')->with('project/tool', 3583118)->willReturn((object) [
            'iid' => 3583118,
            'title' => self::MARKUP,
            'description' => self::MARKUP,
            'state' => 'opened',
            'labels' => [],
            'created_at' => '2026-10-23T00:00:00Z',
            'updated_at' => '2026-10-23T00:00:00Z',
            'web_url' => 'https://git.drupalcode.org/project/tool/-/work_items/3583118',
            'author' => (object) ['username' => 'reporter'],
            'assignees' => [],
        ]);
        $gitLabClient->method('getIssueNotes')->with('project/tool', 3583118)->willReturn([
            (object) [
                'id' => 1,
                'body' => self::MARKUP,
                'system' => false,
                'author' => (object) ['username' => 'reviewer'],
                'created_at' => '2026-10-23T01:00:00Z',
            ],
        ]);

        $command = new class ($gitLabClient) extends Show {
            public function __construct(private readonly GitLabClient $gitLabClient)
            {
                parent::__construct();
            }

            protected function createGitLabClient(): GitLabClient
            {
                return $this->gitLabClient;
            }
        };
        $tester = new CommandTester($command);

        $tester->execute(['nid' => 'tool#3583118', '--with-comments' => true]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString('Title: ' . self::MARKUP . "\n", $display);
        self::assertStringContainsString("Description:\n" . self::MARKUP . "\n", $display);
        self::assertStringContainsString("Comment #1 by reviewer (2026-10-23T01:00:00Z):\n" . self::MARKUP . "\n", $display);
    }
}
