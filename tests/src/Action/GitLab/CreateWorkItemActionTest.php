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
            ->with('project/tool', 'Add a dry run flag', "## Problem\n\n`code`", ['category::feature', 'state::needsReview'], false)
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

    public function testSendsConfidential(): void
    {
        $issue = new \stdClass();
        $issue->iid = 12;

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('createIssue')
            ->with('project/tool', 'Title', 'Body', [], true)
            ->willReturn($issue);

        $result = (new CreateWorkItemAction($this->client(), $gitLabClient))('tool', 'Title', 'Body', confidential: true);

        self::assertTrue($result->confidential);
        self::assertTrue($result->jsonSerialize()['confidential']);
    }

    public function testRejectsUnknownLabelWithSuggestion(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getLabelNames')->willReturn(['category::bug', 'state::needsReview']);
        $gitLabClient->expects(self::never())->method('createIssue');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('project/tool has no label state::needsreview (did you mean state::needsReview?), nonsense.');
        (new CreateWorkItemAction($this->client(), $gitLabClient))(
            'tool',
            'Title',
            category: ProjectIssueCategory::Bug,
            labels: ['state::needsreview', 'nonsense'],
            dryRun: true,
        );
    }

    public function testSendsLabelsUncheckedWhenLabelListFails(): void
    {
        $issue = new \stdClass();
        $issue->iid = 12;

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getLabelNames')->willThrowException(new \Exception('Unauthorized', 401));
        $gitLabClient->expects(self::once())->method('createIssue')->willReturn($issue);

        $result = (new CreateWorkItemAction($this->client(), $gitLabClient))('tool', 'Title', labels: ['anything']);

        self::assertSame(['anything'], $result->labels);
    }

    public function testDryRunReportsDefaultTemplateAndNearIdenticalTitles(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssueTemplateNames')->with('project/tool')->willReturn(['Bug', 'Default']);
        $gitLabClient->expects(self::once())
            ->method('getIssues')
            ->with('project/tool', self::callback(static fn(array $params) => $params['search'] === 'Add a dry run flag'
                && $params['state'] === 'opened'
                && $params['in'] === 'title'))
            ->willReturn([
                (object) ['iid' => 7, 'title' => 'Add a  dry-run flag', 'web_url' => 'https://example.com/7'],
                (object) ['iid' => 8, 'title' => 'Add a dry run flag to the importer and exporter commands', 'web_url' => 'https://example.com/8'],
            ]);

        $result = (new CreateWorkItemAction($this->client(), $gitLabClient))('tool', 'Add a dry run flag', dryRun: true);

        self::assertTrue($result->usesDefaultTemplate);
        self::assertStringContainsString('drupalorg issue:template project/tool', (string) $result->templateNotice());
        self::assertCount(1, $result->possibleDuplicates);
        self::assertSame(7, $result->possibleDuplicates[0]->iid);
        self::assertSame(
            [['issue_iid' => 7, 'title' => 'Add a  dry-run flag', 'url' => 'https://example.com/7']],
            $result->jsonSerialize()['possible_duplicates'],
        );
    }

    public function testDryRunWithDescriptionSkipsTemplateCheck(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::never())->method('getIssueTemplateNames');

        $result = (new CreateWorkItemAction($this->client(), $gitLabClient))('tool', 'Title', 'Body', dryRun: true);

        self::assertFalse($result->usesDefaultTemplate);
        self::assertNull($result->templateNotice());
    }

    public function testDryRunIgnoresLookupFailures(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssueTemplateNames')->willThrowException(new \Exception('Not found', 404));
        $gitLabClient->method('getIssues')->willThrowException(new \Exception('Error', 500));

        $result = (new CreateWorkItemAction($this->client(), $gitLabClient))('tool', 'Title', dryRun: true);

        self::assertFalse($result->usesDefaultTemplate);
        self::assertSame([], $result->possibleDuplicates);
    }

    public function testFlagsTemplateGitLabFilledIn(): void
    {
        $issue = new \stdClass();
        $issue->iid = 12;
        $issue->description = '<!-- Delete these comments as you go -->';

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('createIssue')->willReturn($issue);
        $gitLabClient->expects(self::never())->method('getIssues');

        $result = (new CreateWorkItemAction($this->client(), $gitLabClient))('tool', 'Title');

        self::assertTrue($result->usesDefaultTemplate);
        self::assertSame('', $result->description);
        self::assertStringContainsString(
            'Edit it at https://git.drupalcode.org/project/tool/-/work_items/12',
            (string) $result->templateNotice(),
        );
    }
}
