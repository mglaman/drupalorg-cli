<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Command\Issue;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrgCli\Command\Issue\Template;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(Template::class)]
class TemplateTest extends TestCase
{
    /**
     * @param string[] $templates
     */
    private function tester(array $templates = ['Default']): CommandTester
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssueTemplateNames')->willReturn($templates);
        $gitLabClient->method('getIssueTemplate')->willReturn("<!-- Delete these comments -->\n## Problem/Motivation\n");

        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn(new Project('3346420', 'Tool API', 'tool', false));

        $command = new class ($gitLabClient, $client) extends Template {
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

    public function testPrintsTemplateVerbatim(): void
    {
        $tester = $this->tester();
        $tester->execute(['project' => 'tool']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame("<!-- Delete these comments -->\n## Problem/Motivation\n", $tester->getDisplay());
    }

    public function testReportsMissingDefaultTemplate(): void
    {
        $tester = $this->tester(['Bug']);
        $tester->execute(['project' => 'tool'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('', $tester->getDisplay());
        self::assertSame("project/tool has no Default issue template. Available: Bug.\n", $tester->getErrorOutput());
    }

    public function testJsonFormat(): void
    {
        $tester = $this->tester();
        $tester->execute(['project' => 'tool', '--format' => 'json']);

        $output = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($output);
        self::assertSame('Default', $output['name']);
        self::assertSame(['Default'], $output['available']);
    }
}
