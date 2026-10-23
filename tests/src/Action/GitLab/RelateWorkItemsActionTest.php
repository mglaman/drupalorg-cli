<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Action\GitLab;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use mglaman\DrupalOrg\Action\GitLab\RelateWorkItemsAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\IssueLinkType;
use mglaman\DrupalOrg\Result\Issue\WorkItemLinkResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelateWorkItemsAction::class)]
#[CoversClass(WorkItemLinkResult::class)]
class RelateWorkItemsActionTest extends TestCase
{
    private static function project(int $id): \stdClass
    {
        $project = new \stdClass();
        $project->id = $id;
        return $project;
    }

    public function testLinksWorkItemsInTheSameProjectWithOneLookup(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('getProject')
            ->with('project/tool')
            ->willReturn(self::project(152345));
        $gitLabClient->expects(self::once())
            ->method('postIssueLink')
            ->with(152345, 3583118, 152345, 3582968, IssueLinkType::RelatesTo)
            ->willReturn(new \stdClass());

        $action = new RelateWorkItemsAction($this->createMock(Client::class), $gitLabClient);
        $result = $action('tool#3583118', 'tool#3582968', IssueLinkType::RelatesTo);

        self::assertSame('project/tool', $result->sourceProjectPath);
        self::assertSame(3583118, $result->sourceIid);
        self::assertSame('relates_to', $result->linkType);
        self::assertSame('project/tool', $result->targetProjectPath);
        self::assertSame(3582968, $result->targetIid);
        self::assertSame('https://git.drupalcode.org/project/tool/-/work_items/3582968', $result->targetUrl());
    }

    public function testLinksWorkItemsAcrossProjects(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::exactly(2))
            ->method('getProject')
            ->willReturnMap([
                ['project/mcp_server_tool_bridge', self::project(200)],
                ['project/mcp_server', self::project(100)],
            ]);
        $gitLabClient->expects(self::once())
            ->method('postIssueLink')
            ->with(200, 3618722, 100, 3585914, IssueLinkType::IsBlockedBy)
            ->willReturn(new \stdClass());

        $action = new RelateWorkItemsAction($this->createMock(Client::class), $gitLabClient);
        $result = $action('mcp_server_tool_bridge#3618722', 'mcp_server#3585914', IssueLinkType::IsBlockedBy);

        self::assertSame('is_blocked_by', $result->linkType);
        self::assertSame('project/mcp_server', $result->targetProjectPath);
    }

    public function testForbiddenSuggestsCommentingOnEachWorkItem(): void
    {
        $forbidden = new ClientException(
            'You must be a member of the project or group of both issues',
            new Request('POST', 'projects/200/issues/3618722/links'),
            new Response(403),
        );

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::project(200));
        $gitLabClient->method('postIssueLink')->willThrowException($forbidden);

        $action = new RelateWorkItemsAction($this->createMock(Client::class), $gitLabClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessageMatches(
            "/drupalorg issue:comment 'mcp_server_tool_bridge#3618722' --message=\"Related to mcp_server#3585914\.\"/"
        );
        $action('mcp_server_tool_bridge#3618722', 'mcp_server#3585914', IssueLinkType::RelatesTo);
    }

    public function testRejectsLinkingAWorkItemToItself(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::never())->method('postIssueLink');

        $action = new RelateWorkItemsAction($this->createMock(Client::class), $gitLabClient);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot link a work item to itself.');
        $action('tool#3583118', 'https://git.drupalcode.org/project/tool/-/work_items/3583118', IssueLinkType::RelatesTo);
    }

    public function testThrowsWhenProjectResponseLacksId(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(new \stdClass());
        $gitLabClient->expects(self::never())->method('postIssueLink');

        $action = new RelateWorkItemsAction($this->createMock(Client::class), $gitLabClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('GitLab project response for project/tool did not contain an id.');
        $action('tool#3583118', 'tool#3582968', IssueLinkType::RelatesTo);
    }
}
