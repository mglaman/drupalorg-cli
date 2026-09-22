<?php

namespace mglaman\DrupalOrg\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\MigratedIssueException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(Client::class)]
class ClientTest extends TestCase
{
    /**
     * Every request the client sent, recorded by Guzzle's history middleware.
     *
     * @var array<int, array{request: RequestInterface}>
     */
    private array $history = [];

    private MockHandler $responses;

    private function client(bool $noCache, Response ...$responses): Client
    {
        $this->responses = new MockHandler($responses);
        $stack = HandlerStack::create($this->responses);
        $stack->push(Middleware::history($this->history));
        return new Client($noCache, $stack);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function clientResponding(array $body): Client
    {
        return $this->client(false, self::json($body));
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private static function json(array $body, array $headers = []): Response
    {
        return new Response(
            200,
            $headers + ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR)
        );
    }

    private function sentRequest(int $index = 0): RequestInterface
    {
        self::assertArrayHasKey($index, $this->history, sprintf('Expected request %d to have been sent.', $index));
        return $this->history[$index]['request'];
    }

    public function testSendsDefaultHeaders(): void
    {
        $this->clientResponding(['nid' => '1', 'type' => 'project_issue'])->getNode('1');

        $request = $this->sentRequest();
        self::assertSame('DrupalOrgCli/0.0.1', $request->getHeaderLine('User-Agent'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('*', $request->getHeaderLine('Accept-Encoding'));
        self::assertFalse($request->hasHeader('Cache-Control'));
        self::assertFalse($request->hasHeader('Pragma'));
    }

    public function testSendsNoCacheHeadersWhenAsked(): void
    {
        $this->client(true, self::json(['nid' => '1', 'type' => 'project_issue']))->getNode('1');

        $request = $this->sentRequest();
        self::assertSame('no-cache, no-store, max-age=0', $request->getHeaderLine('Cache-Control'));
        self::assertSame('no-cache', $request->getHeaderLine('Pragma'));
    }

    public function testRequestsNodesFromTheApiEndpoint(): void
    {
        $this->clientResponding(['nid' => '3383637', 'type' => 'project_issue'])->getNode('3383637');

        self::assertSame('https://www.drupal.org/api-d7/node/3383637', (string) $this->sentRequest()->getUri());
    }

    public function testRequestsProjectsByMachineName(): void
    {
        $this->clientResponding(['list' => []])->getProject('token');

        self::assertSame(
            'https://www.drupal.org/api-d7/node.json?field_project_machine_name=token',
            (string) $this->sentRequest()->getUri()
        );
    }

    public function testKeepsCookiesBetweenRequests(): void
    {
        $client = $this->client(
            false,
            self::json(['list' => []], ['Set-Cookie' => 'SESS=abc123; Path=/']),
            self::json(['list' => []]),
        );

        $client->getProject('token');
        $client->getProject('token');

        self::assertFalse($this->sentRequest(0)->hasHeader('Cookie'));
        self::assertSame('SESS=abc123', $this->sentRequest(1)->getHeaderLine('Cookie'));
    }

    public function testRetriesAfterServiceUnavailable(): void
    {
        $project = $this->client(
            false,
            new Response(503, ['Retry-After' => '0']),
            self::json(['list' => [['nid' => '3060', 'title' => 'Drupal core']]]),
        )->getProject('drupal');

        self::assertNotNull($project);
        self::assertSame('3060', $project->nid);
        self::assertSame(0, $this->responses->count(), 'Both the 503 and the 200 should have been consumed.');
    }

    public function testGetNodeReturnsIssue(): void
    {
        $client = $this->clientResponding([
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
        $client = $this->clientResponding([
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
        $client = $this->clientResponding([
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
        $client = $this->clientResponding(['comments' => [], 'body' => []]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Node 999999999 was not found on Drupal.org.');
        $client->getNode('999999999');
    }
}
