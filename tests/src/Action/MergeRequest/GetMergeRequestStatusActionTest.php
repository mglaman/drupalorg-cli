<?php

namespace mglaman\DrupalOrg\Tests\Action\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestStatusAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestStatusResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetMergeRequestStatusAction::class)]
#[CoversClass(MergeRequestStatusResult::class)]
class GetMergeRequestStatusActionTest extends TestCase
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

    private static function makePipeline(string $status): \stdClass
    {
        $pipeline = new \stdClass();
        $pipeline->id = 99;
        $pipeline->status = $status;
        $pipeline->web_url = 'https://git.drupalcode.org/issue/drupal-3383637/-/pipelines/99';
        return $pipeline;
    }

    public function testEmptyPipelines(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequestPipelines')->willReturn([]);

        $action = new GetMergeRequestStatusAction($client, $gitLabClient);
        $result = $action('3383637', 42);

        self::assertSame(42, $result->iid);
        self::assertSame('none', $result->status);
        self::assertNull($result->pipelineId);
        self::assertNull($result->pipelineUrl);
    }

    #[DataProvider('statusMappingProvider')]
    public function testStatusMapping(string $rawStatus, string $expectedStatus): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequestPipelines')
            ->willReturn([self::makePipeline($rawStatus)]);

        $action = new GetMergeRequestStatusAction($client, $gitLabClient);
        $result = $action('3383637', 42);

        self::assertSame($expectedStatus, $result->status);
        self::assertSame(99, $result->pipelineId);
        self::assertSame(
            'https://git.drupalcode.org/issue/drupal-3383637/-/pipelines/99',
            $result->pipelineUrl
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function statusMappingProvider(): array
    {
        return [
            'success maps to passed'                    => ['success', 'passed'],
            'failed maps to failed'                     => ['failed', 'failed'],
            'running maps to running'                   => ['running', 'running'],
            'pending maps to pending'                   => ['pending', 'pending'],
            'canceled maps to canceled'                 => ['canceled', 'canceled'],
            'created maps to pending'                   => ['created', 'pending'],
            'waiting_for_resource maps to pending'      => ['waiting_for_resource', 'pending'],
            'preparing maps to pending'                 => ['preparing', 'pending'],
            'scheduled maps to pending'                 => ['scheduled', 'pending'],
            'skipped maps to canceled'                  => ['skipped', 'canceled'],
            'manual maps to pending'                    => ['manual', 'pending'],
            'unknown status passes through'             => ['unknown_status', 'unknown_status'],
        ];
    }
}
