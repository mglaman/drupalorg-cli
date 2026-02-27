<?php

namespace mglaman\DrupalOrg\Tests\Action\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\ListMergeRequestsAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Enum\MergeRequestState;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestItem;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestListResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListMergeRequestsAction::class)]
#[CoversClass(MergeRequestListResult::class)]
#[CoversClass(MergeRequestItem::class)]
class ListMergeRequestsActionTest extends TestCase
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

    private static function makeProject(): \stdClass
    {
        $project = new \stdClass();
        $project->id = 12345;
        return $project;
    }

    private static function makeMrObject(int $iid = 7, string $state = 'opened'): \stdClass
    {
        $author = new \stdClass();
        $author->username = 'mglaman';

        $mr = new \stdClass();
        $mr->iid = $iid;
        $mr->title = 'Fix the bug';
        $mr->source_branch = '3383637-fix-the-bug';
        $mr->target_branch = '11.x';
        $mr->state = $state;
        $mr->web_url = 'https://git.drupalcode.org/issue/drupal-3383637/-/merge_requests/' . $iid;
        $mr->merge_status = 'can_be_merged';
        $mr->author = $author;
        $mr->updated_at = '2024-01-15T10:00:00Z';
        return $mr;
    }

    public function testListWithStateFilter(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->with('3383637')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->with('issue/drupal-3383637')->willReturn(self::makeProject());
        $gitLabClient->expects($this->once())
            ->method('getMergeRequests')
            ->with(12345, ['per_page' => 100, 'state' => 'opened'])
            ->willReturn([self::makeMrObject()]);

        $action = new ListMergeRequestsAction($client, $gitLabClient);
        $result = $action('3383637', MergeRequestState::Opened);

        self::assertInstanceOf(MergeRequestListResult::class, $result);
        self::assertSame('issue/drupal-3383637', $result->projectPath);
        self::assertCount(1, $result->mergeRequests);
        self::assertInstanceOf(MergeRequestItem::class, $result->mergeRequests[0]);
        self::assertSame(7, $result->mergeRequests[0]->iid);
        self::assertSame('opened', $result->mergeRequests[0]->state);
        self::assertSame('mglaman', $result->mergeRequests[0]->author);
        self::assertTrue($result->mergeRequests[0]->isMergeable);
    }

    public function testAllStateOmitsStateParam(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->expects($this->once())
            ->method('getMergeRequests')
            ->with(12345, ['per_page' => 100])
            ->willReturn([self::makeMrObject(7, 'opened'), self::makeMrObject(6, 'merged')]);

        $action = new ListMergeRequestsAction($client, $gitLabClient);
        $result = $action('3383637', MergeRequestState::All);

        self::assertCount(2, $result->mergeRequests);
    }

    public function testEmptyResult(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequests')->willReturn([]);

        $action = new ListMergeRequestsAction($client, $gitLabClient);
        $result = $action('3383637', MergeRequestState::Opened);

        self::assertSame([], $result->mergeRequests);
    }
}
