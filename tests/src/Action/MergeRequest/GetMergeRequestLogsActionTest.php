<?php

namespace mglaman\DrupalOrg\Tests\Action\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestLogsAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestLogsResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetMergeRequestLogsAction::class)]
#[CoversClass(MergeRequestLogsResult::class)]
class GetMergeRequestLogsActionTest extends TestCase
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
        $project->id = self::TARGET_PROJECT_ID;
        return $project;
    }

    private const TARGET_PROJECT_ID = 12345;
    private const FORK_PROJECT_ID = 242679;

    private static function makePipeline(int $id = 99): \stdClass
    {
        $pipeline = new \stdClass();
        $pipeline->id = $id;
        $pipeline->status = 'failed';
        $pipeline->project_id = self::FORK_PROJECT_ID;
        return $pipeline;
    }

    private static function makeJob(int $id, string $name, string $status, ?string $webUrl = null): \stdClass
    {
        $job = new \stdClass();
        $job->id = $id;
        $job->name = $name;
        $job->status = $status;
        if ($webUrl !== null) {
            $job->web_url = $webUrl;
        }
        return $job;
    }

    public function testEmptyPipelines(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequestPipelines')->willReturn([]);

        $action = new GetMergeRequestLogsAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertSame(7, $result->iid);
        self::assertNull($result->pipelineId);
        self::assertSame([], $result->failedJobs);
    }

    public function testNoFailedJobs(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequestPipelines')->willReturn([self::makePipeline()]);
        $gitLabClient->method('getPipelineJobs')->willReturn([
            self::makeJob(1, 'phpstan', 'success'),
            self::makeJob(2, 'phpunit', 'success'),
        ]);

        $action = new GetMergeRequestLogsAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertSame(99, $result->pipelineId);
        self::assertSame([], $result->failedJobs);
    }

    public function testFailedJobWithTrace(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $trace = implode("\n", array_fill(0, 120, 'log line')) . "\nFATAL ERROR";

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequestPipelines')->with(self::TARGET_PROJECT_ID, 7)->willReturn([self::makePipeline()]);
        $gitLabClient->method('getPipelineJobs')->with(self::FORK_PROJECT_ID, 99)->willReturn([
            self::makeJob(1, 'phpunit', 'success'),
            self::makeJob(2, 'phpstan', 'failed'),
        ]);
        $gitLabClient->method('getJobTrace')->with(self::FORK_PROJECT_ID, 2)->willReturn($trace);

        $action = new GetMergeRequestLogsAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertCount(1, $result->failedJobs);
        self::assertSame('phpstan', $result->failedJobs[0]['name']);
        self::assertStringContainsString('FATAL ERROR', $result->failedJobs[0]['trace_excerpt']);
        // Excerpt is capped at 100 lines.
        self::assertCount(100, explode("\n", $result->failedJobs[0]['trace_excerpt']));
    }

    public function testFailedJobTraceFallsBackToRawLog(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $webUrl = 'https://git.drupalcode.org/issue/poll-3620831/-/jobs/2';

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequestPipelines')->willReturn([self::makePipeline()]);
        $gitLabClient->method('getPipelineJobs')->willReturn([
            self::makeJob(2, 'phpstan', 'failed', $webUrl),
        ]);
        $gitLabClient->method('getJobTrace')->willThrowException(new \Exception('401 Unauthorized'));
        $gitLabClient->method('getJobRawLog')->with($webUrl)->willReturn("line one\nERROR: Job failed");

        $action = new GetMergeRequestLogsAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertCount(1, $result->failedJobs);
        self::assertSame("line one\nERROR: Job failed", $result->failedJobs[0]['trace_excerpt']);
    }

    public function testFailedJobWithUnavailableTrace(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequestPipelines')->willReturn([self::makePipeline()]);
        $gitLabClient->method('getPipelineJobs')->willReturn([
            self::makeJob(2, 'phpstan', 'failed'),
        ]);
        $gitLabClient->method('getJobTrace')->willThrowException(new \Exception('403 Forbidden'));
        $gitLabClient->expects(self::never())->method('getJobRawLog');

        $action = new GetMergeRequestLogsAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertCount(1, $result->failedJobs);
        self::assertSame('(trace unavailable)', $result->failedJobs[0]['trace_excerpt']);
    }

    public function testFailedJobWithUnavailableTraceAndRawLog(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequestPipelines')->willReturn([self::makePipeline()]);
        $gitLabClient->method('getPipelineJobs')->willReturn([
            self::makeJob(2, 'phpstan', 'failed', 'https://git.drupalcode.org/issue/poll-3620831/-/jobs/2'),
        ]);
        $gitLabClient->method('getJobTrace')->willThrowException(new \Exception('401 Unauthorized'));
        $gitLabClient->method('getJobRawLog')->willThrowException(new \Exception('404 Not Found'));

        $action = new GetMergeRequestLogsAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertSame('(trace unavailable)', $result->failedJobs[0]['trace_excerpt']);
    }
}
