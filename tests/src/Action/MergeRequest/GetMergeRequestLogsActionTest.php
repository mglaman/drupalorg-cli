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
        $project->id = 12345;
        return $project;
    }

    private static function makePipeline(int $id = 99): \stdClass
    {
        $pipeline = new \stdClass();
        $pipeline->id = $id;
        $pipeline->status = 'failed';
        return $pipeline;
    }

    private static function makeJob(int $id, string $name, string $status): \stdClass
    {
        $job = new \stdClass();
        $job->id = $id;
        $job->name = $name;
        $job->status = $status;
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
        $gitLabClient->method('getMergeRequestPipelines')->willReturn([self::makePipeline()]);
        $gitLabClient->method('getPipelineJobs')->willReturn([
            self::makeJob(1, 'phpunit', 'success'),
            self::makeJob(2, 'phpstan', 'failed'),
        ]);
        $gitLabClient->method('getJobTrace')->with(12345, 2)->willReturn($trace);

        $action = new GetMergeRequestLogsAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertCount(1, $result->failedJobs);
        self::assertSame('phpstan', $result->failedJobs[0]['name']);
        self::assertStringContainsString('FATAL ERROR', $result->failedJobs[0]['trace_excerpt']);
        // Excerpt is capped at 100 lines.
        self::assertCount(100, explode("\n", $result->failedJobs[0]['trace_excerpt']));
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

        $action = new GetMergeRequestLogsAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertCount(1, $result->failedJobs);
        self::assertSame('(trace unavailable)', $result->failedJobs[0]['trace_excerpt']);
    }
}
