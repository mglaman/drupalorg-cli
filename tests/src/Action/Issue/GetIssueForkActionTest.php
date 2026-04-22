<?php

namespace mglaman\DrupalOrg\Tests\Action\Issue;

use mglaman\DrupalOrg\Action\Issue\GetIssueForkAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\Issue\IssueForkResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetIssueForkAction::class)]
#[CoversClass(IssueForkResult::class)]
class GetIssueForkActionTest extends TestCase
{
    private static function makeIssueNode(): IssueNode
    {
        return new IssueNode(
            nid: '3383637',
            title: 'Test issue',
            created: 1693195104,
            changed: 1727653295,
            commentCount: 0,
            fieldIssueVersion: '11.x-dev',
            fieldIssueStatus: 1,
            fieldIssueCategory: 1,
            fieldIssuePriority: 200,
            fieldIssueComponent: 'Base system',
            fieldProjectId: '3060',
            fieldProjectMachineName: 'drupal',
            bodyValue: null,
            authorId: null,
            fieldIssueFiles: [],
            comments: [],
        );
    }

    public function testForkWithBranches(): void
    {
        $project = new \stdClass();
        $project->id = 12345;

        $branch1 = new \stdClass();
        $branch1->name = '3383637-test-issue';
        $branch2 = new \stdClass();
        $branch2->name = 'main';

        $client = $this->createMock(Client::class);
        $client->method('getNode')->with('3383637')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->with('issue/drupal-3383637')->willReturn($project);
        $gitLabClient->method('getBranches')->with(12345)->willReturn([$branch1, $branch2]);

        $action = new GetIssueForkAction($client, $gitLabClient);
        $result = $action('3383637');

        self::assertInstanceOf(IssueForkResult::class, $result);
        self::assertSame('drupal-3383637', $result->remoteName);
        self::assertSame('git@git.drupal.org:issue/drupal-3383637.git', $result->sshUrl);
        self::assertSame('https://git.drupalcode.org/issue/drupal-3383637.git', $result->httpsUrl);
        self::assertSame('issue/drupal-3383637', $result->gitLabProjectPath);
        self::assertSame(['3383637-test-issue', 'main'], $result->branches);
    }

    public function testForkNotYetCreated(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->with('3383637')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willThrowException(new \Exception('Not Found', 404));

        $action = new GetIssueForkAction($client, $gitLabClient);
        $result = $action('3383637');

        self::assertInstanceOf(IssueForkResult::class, $result);
        self::assertSame('drupal-3383637', $result->remoteName);
        self::assertSame('git@git.drupal.org:issue/drupal-3383637.git', $result->sshUrl);
        self::assertSame('issue/drupal-3383637', $result->gitLabProjectPath);
        self::assertSame([], $result->branches);
    }
}
