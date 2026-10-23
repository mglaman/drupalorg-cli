<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Command\Issue;

use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrgCli\Command\Issue\Comment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(Comment::class)]
class CommentTest extends TestCase
{
    /**
     * The body each test's command posted, captured from the GitLab client.
     */
    private ?string $postedBody = null;

    private function tester(): CommandTester
    {
        $note = new \stdClass();
        $note->id = 3625786;

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('postIssueNote')
            ->willReturnCallback(function (string $projectPath, int $iid, string $body) use ($note): \stdClass {
                $this->postedBody = $body;
                return $note;
            });

        $command = new class ($gitLabClient) extends Comment {
            public function __construct(private readonly GitLabClient $gitLabClient)
            {
                parent::__construct();
            }

            protected function createGitLabClient(): GitLabClient
            {
                return $this->gitLabClient;
            }
        };
        return new CommandTester($command);
    }

    public function testPostsMessage(): void
    {
        $tester = $this->tester();
        $tester->execute(['ref' => 'tool#3583107', '--message' => 'Thanks for retesting.']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('Thanks for retesting.', $this->postedBody);
        self::assertSame(
            "Posted comment: https://git.drupalcode.org/project/tool/-/work_items/3583107#note_3625786\n",
            $tester->getDisplay(),
        );
    }

    public function testPostsFileContents(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'note');
        self::assertIsString($file);
        file_put_contents($file, "## Review\n\nUse `drupalorg issue:show` first.\n");

        $tester = $this->tester();
        $tester->execute(['ref' => 'tool#3583107', '--file' => $file]);
        unlink($file);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame("## Review\n\nUse `drupalorg issue:show` first.\n", $this->postedBody);
    }

    public function testPostsStdinWhenFileIsDash(): void
    {
        $tester = $this->tester();
        $tester->setInputs(["Piped \"note\" body"]);
        $tester->execute(['ref' => 'tool#3583107', '--file' => '-']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame("Piped \"note\" body\n", $this->postedBody);
    }

    public function testJsonFormatIncludesNoteId(): void
    {
        $tester = $this->tester();
        $tester->execute(['ref' => 'tool#3583107', '--message' => 'Hello', '--format' => 'json']);

        $output = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($output);
        self::assertSame(3625786, $output['note_id']);
        self::assertSame(
            'https://git.drupalcode.org/project/tool/-/work_items/3583107#note_3625786',
            $output['url'],
        );
    }

    public function testRequiresMessageOrFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Pass exactly one of --message or --file.');
        $this->tester()->execute(['ref' => 'tool#3583107']);
    }

    public function testRejectsBothMessageAndFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Pass exactly one of --message or --file.');
        $this->tester()->execute(['ref' => 'tool#3583107', '--message' => 'Hello', '--file' => '-']);
    }

    public function testRejectsUnreadableFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not read the comment body from /nonexistent/note.md.');
        $this->tester()->execute(['ref' => 'tool#3583107', '--file' => '/nonexistent/note.md']);
    }
}
