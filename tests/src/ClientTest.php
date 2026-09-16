<?php

namespace mglaman\DrupalOrg\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\MigratedIssueException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Client::class)]
class ClientTest extends TestCase
{
    /**
     * @param array<string, mixed> $body
     */
    private static function clientResponding(array $body): Client
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR)),
        ]));
        return new class ($handler) extends Client {
            public function __construct(HandlerStack $handler)
            {
                parent::__construct();
                $this->client = new \GuzzleHttp\Client(['handler' => $handler]);
            }
        };
    }

    public function testGetNodeReturnsIssue(): void
    {
        $client = self::clientResponding([
            'nid' => '3383637',
            'type' => 'project_issue',
            'title' => 'Fix the thing',
            'field_project' => ['id' => '3060', 'machine_name' => 'drupal'],
        ]);

        $issue = $client->getNode('3383637');

        self::assertSame('3383637', $issue->nid);
        self::assertSame('drupal', $issue->fieldProjectMachineName);
    }

    public function testGetNodeRejectsNonIssueNode(): void
    {
        $client = self::clientResponding([
            'nid' => '3000001',
            'type' => 'project_release',
            'title' => 'queue_throttle 8.x-1.x-dev',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Node 3000001 is a project_release, not an issue.');
        $client->getNode('3000001');
    }

    public function testGetNodeReportsMigratedIssue(): void
    {
        $client = self::clientResponding([
            'new_url' => 'https://git.drupalcode.org/project/restrict_route_by_ip/-/work_items/3617735',
        ]);

        try {
            $client->getNode('3617735');
            self::fail('Expected MigratedIssueException.');
        } catch (MigratedIssueException $e) {
            self::assertSame('3617735', $e->nid);
            self::assertSame('project/restrict_route_by_ip', $e->ref->projectPath);
            self::assertSame(3617735, $e->ref->issueId);
            self::assertSame(
                'Issue 3617735 moved to a GitLab work item at '
                . 'https://git.drupalcode.org/project/restrict_route_by_ip/-/work_items/3617735. '
                . 'Pass restrict_route_by_ip#3617735.',
                $e->getMessage()
            );
        }
    }

    public function testGetNodeRejectsMissingNode(): void
    {
        $client = self::clientResponding(['comments' => [], 'body' => []]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Node 999999999 was not found on Drupal.org.');
        $client->getNode('999999999');
    }
}
