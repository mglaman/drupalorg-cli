<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Command\Issue;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrgCli\Command\Issue\Create;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(Create::class)]
class CreateTest extends TestCase
{
    /**
     * The arguments each test's command sent to createIssue().
     *
     * @var array<int, mixed>|null
     */
    private ?array $created = null;

    private function tester(): CommandTester
    {
        $issue = new \stdClass();
        $issue->iid = 3590001;
        $issue->web_url = 'https://git.drupalcode.org/project/tool/-/work_items/3590001';

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('createIssue')
            ->willReturnCallback(function (...$args) use ($issue): \stdClass {
                $this->created = $args;
                return $issue;
            });

        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn(new Project('3346420', 'Tool API', 'tool', false));

        $command = new class ($gitLabClient, $client) extends Create {
            public function __construct(
                private readonly GitLabClient $gitLabClient,
                private readonly Client $drupalOrgClient,
            ) {
                parent::__construct();
            }

            protected function initialize(InputInterface $input, OutputInterface $output): void
            {
                parent::initialize($input, $output);
                $this->client = $this->drupalOrgClient;
            }

            protected function createGitLabClient(): GitLabClient
            {
                return $this->gitLabClient;
            }
        };
        return new CommandTester($command);
    }

    public function testCreatesIssue(): void
    {
        $tester = $this->tester();
        $tester->execute([
            'project' => 'tool',
            '--title' => 'Add a dry run flag',
            '--message' => 'Body with `code`.',
            '--category' => 'feature',
            '--label' => ['state::needsWork'],
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(
            ['project/tool', 'Add a dry run flag', 'Body with `code`.', ['category::feature', 'state::needsWork']],
            $this->created,
        );
        self::assertSame(
            "Created project/tool#3590001: https://git.drupalcode.org/project/tool/-/work_items/3590001\n",
            $tester->getDisplay(),
        );
    }

    public function testReadsDescriptionFromStdin(): void
    {
        $tester = $this->tester();
        $tester->setInputs(["## Problem/Motivation"]);
        $tester->execute(['project' => 'tool', '--title' => 'Title', '--file' => '-']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertIsArray($this->created);
        self::assertSame("## Problem/Motivation\n", $this->created[2]);
    }

    public function testDescriptionIsOptional(): void
    {
        $tester = $this->tester();
        $tester->execute(['project' => 'tool', '--title' => 'Title']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertIsArray($this->created);
        self::assertSame('', $this->created[2]);
    }

    public function testDryRunPrintsPayloadWithoutCreating(): void
    {
        $tester = $this->tester();
        $tester->execute([
            'project' => 'tool',
            '--title' => 'Title',
            '--message' => 'Body',
            '--category' => 'bug',
            '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertNull($this->created);
        self::assertSame(
            "Dry run: would create an issue in project/tool.\nTitle: Title\nLabels: category::bug\nDescription:\nBody\n",
            $tester->getDisplay(),
        );
    }

    public function testJsonFormat(): void
    {
        $tester = $this->tester();
        $tester->execute(['project' => 'tool', '--title' => 'Title', '--format' => 'json']);

        $output = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($output);
        self::assertSame(3590001, $output['issue_iid']);
        self::assertSame('project/tool#3590001', $output['ref']);
        self::assertFalse($output['dry_run']);
    }

    public function testRequiresTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Pass the issue title with --title.');
        $this->tester()->execute(['project' => 'tool']);
    }

    public function testRejectsUnknownCategory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown category "bogus". Expected one of: bug, task, feature, support, plan.');
        $this->tester()->execute(['project' => 'tool', '--title' => 'Title', '--category' => 'bogus']);
    }

    public function testRejectsBothMessageAndFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Pass exactly one of --message or --file.');
        $this->tester()->execute(['project' => 'tool', '--title' => 'Title', '--message' => 'a', '--file' => '-']);
    }
}
