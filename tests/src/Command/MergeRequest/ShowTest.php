<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Command\MergeRequest;

use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrgCli\Command\MergeRequest\Show;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(Show::class)]
class ShowTest extends TestCase
{
    private function commandTester(GitLabClient $gitLabClient): CommandTester
    {
        $command = new class ($gitLabClient) extends Show {
            public function __construct(private readonly GitLabClient $mockGitLabClient)
            {
                parent::__construct();
            }

            protected function createGitLabClient(): GitLabClient
            {
                return $this->mockGitLabClient;
            }
        };
        return new CommandTester($command);
    }

    private function gitLabClient(): GitLabClient
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->with('project/tool')->willReturn((object) ['id' => 59858]);
        $gitLabClient->method('getMergeRequest')->with(59858, 219)->willReturn((object) [
            'iid' => 219,
            'title' => 'Issue #3625000: Fix the widget',
            'author' => (object) ['username' => 'contributor'],
            'state' => 'opened',
            'draft' => false,
            'source_branch' => '3625000-fix-widget',
            'target_branch' => '1.x',
            'sha' => 'abc123',
            'web_url' => 'https://git.drupalcode.org/project/tool/-/merge_requests/219',
            'detailed_merge_status' => 'mergeable',
            'has_conflicts' => false,
            'blocking_discussions_resolved' => true,
            'head_pipeline' => null,
        ]);
        $gitLabClient->method('getMergeRequestClosesIssues')->willReturn([]);
        $gitLabClient->method('getMergeRequestNotes')->willReturn([
            (object) [
                'id' => 3625786,
                'body' => 'Drop the <comment> wrapper and null-check Drupal\<Widget>.',
                'author' => (object) ['username' => 'reviewer'],
                'created_at' => '2026-10-20T09:00:00Z',
                'position' => (object) ['new_path' => 'src/Widget.php', 'new_line' => 42],
            ],
        ]);
        return $gitLabClient;
    }

    public function testNoteUrlShowsTheLinkedNote(): void
    {
        $tester = $this->commandTester($this->gitLabClient());

        $tester->execute(['nid' => 'https://git.drupalcode.org/project/tool/-/merge_requests/219#note_3625786']);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString('MR !219: Issue #3625000: Fix the widget', $display);
        self::assertStringContainsString('Merge status: mergeable', $display);
        self::assertStringContainsString('Pipeline: none', $display);
        self::assertStringContainsString("Closes issues:\n  none", $display);
        self::assertStringContainsString("Note 3625786 by reviewer (2026-10-20T09:00:00Z) [linked note]:\nOn src/Widget.php:42\nDrop the <comment> wrapper and null-check Drupal\\<Widget>.", $display);
    }

    public function testShorthandRefSkipsCommentsByDefault(): void
    {
        $tester = $this->commandTester($this->gitLabClient());

        $tester->execute(['nid' => 'project/tool!219', '--format' => 'llm']);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString('<iid>219</iid>', $display);
        self::assertStringNotContainsString('<comments>', $display);
    }
}
