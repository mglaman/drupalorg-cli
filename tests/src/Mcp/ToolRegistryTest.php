<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Mcp;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Mcp\ToolRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolRegistry::class)]
class ToolRegistryTest extends TestCase
{
    private static function nodeListResponse(object ...$nodes): Response
    {
        return new Response(200, [], json_encode(['list' => $nodes], JSON_THROW_ON_ERROR));
    }

    private static function project(bool $hasIssueQueue): object
    {
        return (object) [
            'nid' => '1234',
            'title' => 'Smart Content',
            'field_project_machine_name' => 'smart_content',
            'field_project_has_issue_queue' => $hasIssueQueue,
        ];
    }

    public function testProjectGetIssuesListsGitLabWorkItemsForMigratedProject(): void
    {
        $http = new MockHandler([self::nodeListResponse(self::project(false))]);
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('getIssues')
            ->with('project/smart_content', [
                'state' => 'opened',
                'per_page' => 5,
                'order_by' => 'created_at',
                'sort' => 'desc',
                'labels' => 'state::rtbc,category::bug',
            ])
            ->willReturn([]);

        $registry = new ToolRegistry(new Client(false, HandlerStack::create($http)), $gitLabClient);
        $result = $registry->projectGetIssues('smart_content', 'rtbc', limit: 5, category: 'bug');

        self::assertSame(['project' => 'smart_content', 'issues' => []], $result);
        self::assertSame(0, $http->count());
    }

    public function testProjectGetIssuesListsDrupalOrgIssuesForProjectWithIssueQueue(): void
    {
        $http = new MockHandler([
            self::nodeListResponse(self::project(true)),
            self::nodeListResponse(),
            self::nodeListResponse(),
        ]);
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::never())->method('getIssues');

        $registry = new ToolRegistry(new Client(false, HandlerStack::create($http)), $gitLabClient);
        $result = $registry->projectGetIssues('smart_content');

        self::assertIsArray($result);
        self::assertSame('Smart Content', $result['project_title'] ?? null);
        self::assertSame(0, $http->count());
    }

    public function testProjectContainsListsRefsForTheProjectRepository(): void
    {
        $http = new MockHandler([self::nodeListResponse(self::project(true))]);
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('getCommit')
            ->with('project/smart_content', 'd6ef1a7')
            ->willReturn((object) [
                'id' => 'd6ef1a7c0ffee',
                'title' => 'Fix decision caching',
                'committed_date' => '2025-03-14T09:26:53.000+00:00',
                'web_url' => 'https://git.drupalcode.org/project/smart_content/-/commit/d6ef1a7c0ffee',
            ]);
        $gitLabClient->expects(self::once())
            ->method('getCommitRefs')
            ->with('project/smart_content', 'd6ef1a7c0ffee')
            ->willReturn([
                (object) ['type' => 'tag', 'name' => '3.0.0'],
                (object) ['type' => 'branch', 'name' => '3.x'],
                (object) ['type' => 'tag', 'name' => '3.0.0-rc1'],
            ]);

        $registry = new ToolRegistry(new Client(false, HandlerStack::create($http)), $gitLabClient);
        $result = $registry->projectContains('smart_content', 'd6ef1a7');

        self::assertIsArray($result);
        self::assertSame('d6ef1a7c0ffee', $result['sha'] ?? null);
        self::assertSame(['3.x'], $result['branches'] ?? null);
        self::assertSame(['3.0.0-rc1', '3.0.0'], $result['tags'] ?? null);
        self::assertSame(0, $http->count());
    }
}
