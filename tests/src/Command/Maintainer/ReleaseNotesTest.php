<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Command\Maintainer;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use mglaman\DrupalOrg\Action\Maintainer\GetMaintainerReleaseNotesAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrgCli\Command\Maintainer\ReleaseNotes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

#[CoversClass(ReleaseNotes::class)]
class ReleaseNotesTest extends TestCase
{
    private string $originalCwd;

    private string $workDir;

    protected function setUp(): void
    {
        $this->originalCwd = (string) getcwd();
        $this->workDir = sys_get_temp_dir() . '/drupalorg-cli-' . uniqid();
        mkdir($this->workDir);
        chdir($this->workDir);

        $this->git('init', '--quiet');
        $this->git('config', 'user.email', 'test@example.com');
        $this->git('config', 'user.name', 'Test User');
        $this->git('config', 'commit.gpgsign', 'false');
        $this->git('remote', 'add', 'origin', 'https://github.com/test/mymodule.git');
        $this->git('commit', '--quiet', '--allow-empty', '-m', 'Initial commit');
        $this->git('tag', '1.0.0');
        $this->git('commit', '--quiet', '--allow-empty', '-m', "Issue #1234567: Don't print <head> & <body> twice by o'brien:");
        $this->git('tag', '1.1.0');
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        (new Process(['rm', '-rf', $this->workDir]))->run();
    }

    private function git(string ...$arguments): void
    {
        (new Process(['git', ...$arguments], $this->workDir))->mustRun();
    }

    private function tester(): CommandTester
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['list' => []])),   // project lookup (null → legacy)
            new Response(200, [], (string) json_encode(['data' => []])),   // contributors (empty)
            new Response(200, [], (string) json_encode(['nid' => '1234567', 'title' => 'Stop printing', 'field_issue_category' => 1])),
        ]));
        $client = $this->createMock(Client::class);
        $client->method('getGuzzleClient')->willReturn(new \GuzzleHttp\Client(['handler' => $handler]));

        $command = new class ($client) extends ReleaseNotes {
            public function __construct(private readonly Client $mockClient)
            {
                parent::__construct();
            }

            protected function createAction(): GetMaintainerReleaseNotesAction
            {
                return new GetMaintainerReleaseNotesAction($this->mockClient);
            }
        };
        return new CommandTester($command);
    }

    public function testHtmlEscapesCommitTitlesAndUsernames(): void
    {
        $tester = $this->tester();

        $tester->execute(['ref1' => '1.0.0', 'ref2' => '1.1.0', '--format' => 'html']);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString(
            "<li><a href=\"https://www.drupal.org/node/1234567\">#1234567</a>: Don't print &lt;head&gt; &amp; &lt;body&gt; twice</li>",
            $display
        );
        self::assertStringContainsString(
            '<a href="https://www.drupal.org/u/o&#039;brien">o&#039;brien</a>',
            $display
        );
        self::assertStringNotContainsString('<head>', $display);
    }
}
