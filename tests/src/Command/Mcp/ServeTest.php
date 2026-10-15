<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Command\Mcp;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mcp\Server\Transport\StdioTransport;
use mglaman\DrupalOrgCli\Command\Mcp\Serve;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Drives the server that mcp:serve runs over in-memory stdio streams, with
 * Drupal.org responses mocked.
 */
#[CoversClass(Serve::class)]
class ServeTest extends TestCase
{
    public function testDiscoversTools(): void
    {
        $responses = $this->runServer(new MockHandler(), [
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
        ]);

        $names = array_column($responses[2]['result']['tools'], 'name');
        self::assertContains('issue_show', $names);
        self::assertContains('mr_list', $names);
    }

    public function testCallsToolOverHttp(): void
    {
        $http = new MockHandler([new Response(200, [], json_encode([
            'nid' => '3383637',
            'type' => 'project_issue',
            'title' => 'Schedule transition button size',
        ], JSON_THROW_ON_ERROR))]);

        $responses = $this->runServer($http, [
            [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => ['name' => 'issue_show', 'arguments' => ['nid' => '3383637']],
            ],
        ]);

        self::assertArrayNotHasKey('error', $responses[2]);
        self::assertSame('Schedule transition button size', $responses[2]['result']['structuredContent']['title']);
        self::assertSame(0, $http->count());
    }

    /**
     * Initializes a session, sends the given requests and returns the
     * responses keyed by request ID.
     *
     * @param list<array<string, mixed>> $requests
     *
     * @return array<int, array<string, mixed>>
     */
    private function runServer(MockHandler $http, array $requests): array
    {
        $messages = [
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 'test', 'version' => '1'],
                ],
            ],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ...$requests,
        ];
        $input = fopen('php://memory', 'r+');
        self::assertIsResource($input);
        foreach ($messages as $message) {
            fwrite($input, json_encode($message, JSON_THROW_ON_ERROR) . "\n");
        }
        rewind($input);
        // The transport closes its output stream when the session ends, so
        // write to a file that can be read back afterwards.
        $outputFile = (string) tempnam(sys_get_temp_dir(), 'drupalorg-cli-mcp');
        $output = fopen($outputFile, 'w');
        self::assertIsResource($output);

        Serve::createServer('test', new NullLogger(), HandlerStack::create($http))
            ->run(new StdioTransport($input, $output));

        $lines = (string) file_get_contents($outputFile);
        unlink($outputFile);
        $responses = [];
        foreach (array_filter(explode("\n", $lines)) as $line) {
            $response = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $responses[$response['id']] = $response;
        }
        return $responses;
    }
}
