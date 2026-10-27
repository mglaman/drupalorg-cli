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
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertFalse($result->alreadyLinked);
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

    private function failingGitLabClient(int $status, string $body): GitLabClient
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::project(200));
        $gitLabClient->method('postIssueLink')->willThrowException(new ClientException(
            'Client error',
            new Request('POST', 'projects/200/issues/3618722/links'),
            new Response($status, ['Content-Type' => 'application/json'], $body),
        ));
        return $gitLabClient;
    }

    /**
     * @return iterable<string, array{IssueLinkType, string, string}>
     */
    public static function forbiddenLinkTypeProvider(): iterable
    {
        yield 'relates_to' => [IssueLinkType::RelatesTo, 'Related to mcp_server#3585914.', 'Related to mcp_server_tool_bridge#3618722.'];
        yield 'blocks' => [IssueLinkType::Blocks, 'Blocks mcp_server#3585914.', 'Blocked by mcp_server_tool_bridge#3618722.'];
        yield 'is_blocked_by' => [IssueLinkType::IsBlockedBy, 'Blocked by mcp_server#3585914.', 'Blocks mcp_server_tool_bridge#3618722.'];
    }

    #[DataProvider('forbiddenLinkTypeProvider')]
    public function testForbiddenSuggestsCommentingOnEachWorkItem(IssueLinkType $linkType, string $sourceNote, string $targetNote): void
    {
        $action = new RelateWorkItemsAction(
            $this->createMock(Client::class),
            $this->failingGitLabClient(403, '{"message":"403 Forbidden"}'),
        );

        try {
            $action('mcp_server_tool_bridge#3618722', 'mcp_server#3585914', $linkType);
            self::fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            self::assertSame(403, $e->getCode());
            self::assertStringContainsString('GitLab refused the link: 403 Forbidden', $e->getMessage());
            self::assertStringContainsString("drupalorg issue:comment 'mcp_server_tool_bridge#3618722' --message=\"{$sourceNote}\"", $e->getMessage());
            self::assertStringContainsString("drupalorg issue:comment 'mcp_server#3585914' --message=\"{$targetNote}\"", $e->getMessage());
        }
    }

    public function testForbiddenOutsideTheProjectNamespaceSuggestsTheWorkItemUrl(): void
    {
        $action = new RelateWorkItemsAction(
            $this->createMock(Client::class),
            $this->failingGitLabClient(403, '{"message":"403 Forbidden"}'),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "drupalorg issue:comment 'https://git.drupalcode.org/sandbox/foo-123/-/work_items/5' --message=\"Related to tool#7.\""
        );
        $action('https://git.drupalcode.org/sandbox/foo-123/-/work_items/5', 'tool#7', IssueLinkType::RelatesTo);
    }

    public function testInsufficientScopeAsksForAnApiToken(): void
    {
        $action = new RelateWorkItemsAction(
            $this->createMock(Client::class),
            $this->failingGitLabClient(403, '{"error":"insufficient_scope","error_description":"The request requires higher privileges than provided by the access token."}'),
        );

        try {
            $action('mcp_server_tool_bridge#3618722', 'mcp_server#3585914', IssueLinkType::RelatesTo);
            self::fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            self::assertSame(
                'GitLab refused the link: your token needs the "api" scope. insufficient_scope: The request requires higher privileges than provided by the access token.',
                $e->getMessage()
            );
        }
    }

    public function testAnExistingLinkIsReportedAsAlreadyLinked(): void
    {
        $action = new RelateWorkItemsAction(
            $this->createMock(Client::class),
            $this->failingGitLabClient(409, '{"message":"Issue(s) already assigned"}'),
        );

        $result = $action('mcp_server_tool_bridge#3618722', 'mcp_server#3585914', IssueLinkType::RelatesTo);

        self::assertTrue($result->alreadyLinked);
        self::assertTrue($result->jsonSerialize()['already_linked']);
    }

    public function testOtherErrorsIncludeTheGitLabMessage(): void
    {
        $action = new RelateWorkItemsAction(
            $this->createMock(Client::class),
            $this->failingGitLabClient(404, '{"message":"No matching issue found. Make sure that you are adding a valid issue URL."}'),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to link project/mcp_server_tool_bridge#3618722 to project/mcp_server#3585914: No matching issue found. Make sure that you are adding a valid issue URL.'
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
