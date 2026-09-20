<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\GitLab;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use mglaman\DrupalOrg\GitLab\Client;
use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(Client::class)]
class ClientTest extends TestCase
{
    private const TOKEN_ENV = 'DRUPALORG_GITLAB_TOKEN';

    private string|false $originalToken;

    private string|false $originalPath;

    /**
     * Every request the client sent, recorded by Guzzle's history middleware.
     *
     * @var array<int, array{request: RequestInterface}>
     */
    private array $history = [];

    private MockHandler $responses;

    protected function setUp(): void
    {
        $this->originalToken = getenv(self::TOKEN_ENV);
        $this->originalPath = getenv('PATH');
        putenv(self::TOKEN_ENV . '=test-token');
    }

    protected function tearDown(): void
    {
        self::restoreEnv(self::TOKEN_ENV, $this->originalToken);
        self::restoreEnv('PATH', $this->originalPath);
    }

    private static function restoreEnv(string $name, string|false $value): void
    {
        putenv($value === false ? $name : $name . '=' . $value);
    }

    private function client(Response ...$responses): Client
    {
        $this->responses = new MockHandler($responses);
        $stack = HandlerStack::create($this->responses);
        $stack->push(Middleware::history($this->history));
        return new Client($stack);
    }

    private static function json(mixed $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function sentRequest(int $index = 0): RequestInterface
    {
        self::assertArrayHasKey($index, $this->history, sprintf('Expected request %d to have been sent.', $index));
        return $this->history[$index]['request'];
    }

    /**
     * @return array<string, string>
     */
    private static function query(RequestInterface $request): array
    {
        parse_str($request->getUri()->getQuery(), $query);
        /** @var array<string, string> $query */
        return $query;
    }

    public function testSendsBearerTokenFromEnvironment(): void
    {
        $this->client(self::json(['id' => 1]))->getProject('project/drupal');

        $request = $this->sentRequest();
        self::assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
        self::assertSame('DrupalOrgCli/0.0.1', $request->getHeaderLine('User-Agent'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function testSendsNoAuthorizationWhenNoTokenIsAvailable(): void
    {
        putenv(self::TOKEN_ENV);
        $emptyBinDir = sys_get_temp_dir() . '/drupalorg-cli-empty-bin';
        if (!is_dir($emptyBinDir)) {
            mkdir($emptyBinDir);
        }
        putenv('PATH=' . $emptyBinDir);

        $this->client(self::json(['id' => 1]))->getProject('project/drupal');

        self::assertFalse($this->sentRequest()->hasHeader('Authorization'));
    }

    public function testGetProjectUrlEncodesThePath(): void
    {
        $this->client(self::json(['id' => 1]))->getProject('project/drupal');

        self::assertSame(
            'https://git.drupalcode.org/api/v4/projects/project%2Fdrupal',
            (string) $this->sentRequest()->getUri()
        );
    }

    public function testGetIssueUrlEncodesThePath(): void
    {
        $this->client(self::json(['iid' => 3617735]))->getIssue('project/restrict_route_by_ip', 3617735);

        self::assertSame(
            'https://git.drupalcode.org/api/v4/projects/project%2Frestrict_route_by_ip/issues/3617735',
            (string) $this->sentRequest()->getUri()
        );
    }

    public function testPostIssueNoteSendsJsonBody(): void
    {
        $this->client(self::json(['id' => 7], 201))->postIssueNote('project/drupal', 12, '/do:fork');

        $request = $this->sentRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://git.drupalcode.org/api/v4/projects/project%2Fdrupal/issues/12/notes',
            (string) $request->getUri()
        );
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"body":"\/do:fork"}', (string) $request->getBody());
    }

    public function testGetIssueNotesStopsAfterAShortPage(): void
    {
        $notes = $this->client(self::json(self::notes(5)))->getIssueNotes('project/drupal', 12);

        self::assertCount(5, $notes);
        self::assertCount(1, $this->history);
        $query = self::query($this->sentRequest());
        self::assertSame('100', $query['per_page']);
        self::assertSame('1', $query['page']);
        self::assertSame('asc', $query['sort']);
        self::assertSame('created_at', $query['order_by']);
    }

    public function testGetIssueNotesFollowsPaginationWhileAPageIsFull(): void
    {
        $notes = $this->client(
            self::json(self::notes(100)),
            self::json(self::notes(3, 100)),
        )->getIssueNotes('project/drupal', 12);

        self::assertCount(103, $notes);
        self::assertCount(2, $this->history);
        self::assertSame('1', self::query($this->sentRequest(0))['page']);
        self::assertSame('2', self::query($this->sentRequest(1))['page']);
    }

    /**
     * @return array<int, array{id: int}>
     */
    private static function notes(int $count, int $firstId = 0): array
    {
        return array_map(static fn(int $id) => ['id' => $id], range($firstId, $firstId + $count - 1));
    }

    public function testGetIssuesByIidMapsFailuresToNull(): void
    {
        $issues = $this->client(
            self::json(['iid' => 1, 'title' => 'First']),
            new Response(404),
            new Response(200, ['Content-Type' => 'application/json'], 'not json'),
        )->getIssuesByIid('project/drupal', [1, 2, 3]);

        self::assertSame([1, 2, 3], array_keys($issues));
        self::assertInstanceOf(GitLabIssue::class, $issues[1]);
        self::assertSame('First', $issues[1]->title);
        self::assertNull($issues[2]);
        self::assertNull($issues[3]);
        self::assertCount(3, $this->history);
        self::assertSame(
            'https://git.drupalcode.org/api/v4/projects/project%2Fdrupal/issues/2',
            (string) $this->sentRequest(1)->getUri()
        );
    }

    public function testGetIssuesByIidSendsNothingForNoIids(): void
    {
        self::assertSame([], $this->client()->getIssuesByIid('project/drupal', []));
        self::assertCount(0, $this->history);
    }

    public function testRetriesAfterServiceUnavailable(): void
    {
        $project = $this->client(
            new Response(503, ['Retry-After' => '0']),
            self::json(['id' => 1]),
        )->getProject('project/drupal');

        self::assertSame(1, $project->id);
        self::assertSame(0, $this->responses->count(), 'Both the 503 and the 200 should have been consumed.');
    }
}
