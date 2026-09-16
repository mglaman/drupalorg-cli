<?php

namespace mglaman\DrupalOrg\Tests\Action\Issue;

use mglaman\DrupalOrg\Action\Issue\SetupIssueRemoteAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SetupIssueRemoteAction::class)]
class SetupIssueRemoteActionTest extends TestCase
{
    public function testMissingForkStopsBeforeTouchingGit(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('getNode');

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willThrowException(new \Exception('Not Found', 404));

        $action = new SetupIssueRemoteAction($client, $gitLabClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No issue fork for 3007808 yet.');
        $action('3007808', 'poll');
    }
}
