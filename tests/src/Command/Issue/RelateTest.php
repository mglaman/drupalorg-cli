<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Command\Issue;

use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\IssueLinkType;
use mglaman\DrupalOrgCli\Command\Issue\Relate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(Relate::class)]
class RelateTest extends TestCase
{
    private function tester(GitLabClient $gitLabClient): CommandTester
    {
        $command = new class ($gitLabClient) extends Relate {
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

    public function testDefaultsToRelatesTo(): void
    {
        $project = new \stdClass();
        $project->id = 152345;

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn($project);
        $gitLabClient->expects(self::once())
            ->method('postIssueLink')
            ->with(152345, 3583118, 152345, 3582968, IssueLinkType::RelatesTo)
            ->willReturn(new \stdClass());

        $tester = $this->tester($gitLabClient);
        $tester->execute(['ref' => 'tool#3583118', 'target' => 'tool#3582968']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame("Linked project/tool#3583118 relates_to project/tool#3582968.\n", $tester->getDisplay());
    }

    public function testRejectsUnknownLinkType(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::never())->method('postIssueLink');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown link type "duplicates". Expected one of: relates_to, blocks, is_blocked_by.');
        $this->tester($gitLabClient)->execute([
            'ref' => 'tool#3583118',
            'target' => 'tool#3582968',
            '--type' => 'duplicates',
        ]);
    }
}
