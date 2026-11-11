<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Action\GitLab;

use mglaman\DrupalOrg\Action\GitLab\CreateWorkItemAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\Enum\ProjectIssueCategory;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\Issue\WorkItemCreatedResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateWorkItemAction::class)]
#[CoversClass(WorkItemCreatedResult::class)]
class CreateWorkItemActionTest extends TestCase
{
    private function client(bool $hasIssueQueue = false): Client
    {
        $client = $this->createMock(Client::class);
        $client->method('getProject')
            ->with('tool')
            ->willReturn(new Project('3346420', 'Tool API', 'tool', $hasIssueQueue));
        return $client;
    }

    public function testCreatesIssueWithCategoryAndLabels(): void
    {
        $issue = new \stdClass();
        $issue->iid = 3590001;
        $issue->web_url = 'https://git.drupalcode.org/project/tool/-/work_items/3590001';

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('createIssue')
            ->with('project/tool', 'Add a dry run flag', "## Problem\n\n`code`", ['category::feature', 'state::needsReview'])
            ->willReturn($issue);

        $action = new CreateWorkItemAction($this->client(), $gitLabClient);
        $result = $action(
            'project/tool',
            '  Add a dry run flag ',
            "## Problem\n\n`code`",
            ProjectIssueCategory::Feature,
            ['state::needsReview', ' ', 'category::feature'],
        );

        self::assertFalse($result->isDryRun());
        self::assertSame(3590001, $result->issueIid);
        self::assertSame('project/tool#3590001', $result->ref());
        self::assertSame('https://git.drupalcode.org/project/tool/-/work_items/3590001', $result->workItemUrl());
        self::assertSame(['category::feature', 'state::needsReview'], $result->labels);
    }

    public function testFallsBackToBuiltUrlWithoutWebUrl(): void
    {
        $issue = new \stdClass();
        $issue->iid = 12;

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('createIssue')->willReturn($issue);

        $result = (new CreateWorkItemAction($this->client(), $gitLabClient))('tool', 'Title');

        self::assertSame('https://git.drupalcode.org/project/tool/-/work_items/12', $result->workItemUrl());
        self::assertSame([], $result->labels);
    }

    public function testDryRunDoesNotPost(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::never())->method('createIssue');

        $result = (new CreateWorkItemAction($this->client(), $gitLabClient))(
            'tool',
            'Title',
            'Body',
            ProjectIssueCategory::Bug,
            dryRun: true,
        );

        self::assertTrue($result->isDryRun());
        self::assertNull($result->ref());
        self::assertNull($result->workItemUrl());
        self::assertSame(['category::bug'], $result->labels);
        self::assertTrue($result->jsonSerialize()['dry_run']);
    }

    public function testRejectsLegacyIssueQueueProject(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::never())->method('createIssue');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('https://www.drupal.org/node/add/project-issue/tool');
        (new CreateWorkItemAction($this->client(true), $gitLabClient))('tool', 'Title');
    }

    public function testRejectsUnknownProject(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Project nope not found.');
        (new CreateWorkItemAction($client, $this->createMock(GitLabClient::class)))('nope', 'Title');
    }

    public function testRejectsInvalidMachineName(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('getProject');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid project machine name "../tool".');
        (new CreateWorkItemAction($client, $this->createMock(GitLabClient::class)))('../tool', 'Title');
    }

    public function testRejectsEmptyTitle(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('getProject');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The issue title is empty.');
        (new CreateWorkItemAction($client, $this->createMock(GitLabClient::class)))('tool', "  \t");
    }

    public function testWrapsGitLabFailure(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('createIssue')->willThrowException(new \Exception('Forbidden', 403));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create an issue in project/tool: Forbidden');
        (new CreateWorkItemAction($this->client(), $gitLabClient))('tool', 'Title');
    }

    public function testThrowsWhenGitLabResponseLacksIid(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('createIssue')->willReturn(new \stdClass());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/did not contain an iid/');
        (new CreateWorkItemAction($this->client(), $gitLabClient))('tool', 'Title');
    }
}
