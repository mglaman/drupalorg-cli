<?php

namespace mglaman\DrupalOrg\Tests\Action\Maintainer;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use mglaman\DrupalOrg\Action\Maintainer\GetMaintainerReleaseNotesAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerReleaseNotesResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversClass(GetMaintainerReleaseNotesAction::class)]
#[CoversClass(MaintainerReleaseNotesResult::class)]
class GetMaintainerReleaseNotesActionTest extends TestCase
{
    private static string $tmpDir = '';
    private static GitRepository $gitRepo;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tmpDir = sys_get_temp_dir() . '/drupalorg-cli-test-' . uniqid();
        mkdir(self::$tmpDir);

        $run = static function (array $cmd): void {
            $p = new Process($cmd, self::$tmpDir);
            $p->mustRun();
        };

        $run(['git', 'init']);
        $run(['git', 'config', 'user.email', 'test@test.com']);
        $run(['git', 'config', 'user.name', 'Test User']);
        $run(['git', 'remote', 'add', 'origin', 'https://github.com/test/mymodule.git']);

        // Initial commit + tag 1.0.0
        file_put_contents(self::$tmpDir . '/README.md', 'Initial');
        $run(['git', 'add', 'README.md']);
        $run(['git', 'commit', '-m', 'Initial commit']);
        $run(['git', 'tag', '1.0.0']);

        // Second commit + tag 1.1.0 (contains NID to test parsing)
        file_put_contents(self::$tmpDir . '/README.md', 'Updated');
        $run(['git', 'add', 'README.md']);
        $run(['git', 'commit', '-m', 'Issue #1234567: Fix the thing by testuser:']);
        $run(['git', 'tag', '1.1.0']);

        $git = new Git();
        self::$gitRepo = $git->open(self::$tmpDir);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (self::$tmpDir !== '') {
            $p = new Process(['rm', '-rf', self::$tmpDir]);
            $p->run();
        }
    }

    private function makeClientWithMockResponses(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $client = $this->createMock(Client::class);
        $client->method('getGuzzleClient')->willReturn($guzzleClient);
        return $client;
    }

    public function testInvokeThrowsOnInvalidRef1(): void
    {
        $repository = $this->createMock(GitRepository::class);
        $repository->method('getTags')->willReturn(['1.0.0', '1.1.0']);

        $client = $this->createMock(Client::class);
        $action = new GetMaintainerReleaseNotesAction($client);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The invalid-tag tag is not valid.');
        $action($repository, self::$tmpDir, 'invalid-tag', '1.1.0');
    }

    public function testInvokeThrowsOnInvalidRef2(): void
    {
        $repository = $this->createMock(GitRepository::class);
        $repository->method('getTags')->willReturn(['1.0.0', '1.1.0']);

        $client = $this->createMock(Client::class);
        $action = new GetMaintainerReleaseNotesAction($client);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The invalid-ref2 tag is not valid.');
        $action($repository, self::$tmpDir, '1.0.0', 'invalid-ref2');
    }

    public function testInvokeRef2HeadAllowed(): void
    {
        // MockHandler responses: contributors for NID 1234567, issue details for NID 1234567, projectId query
        $client = $this->makeClientWithMockResponses([
            new Response(200, [], json_encode(['data' => []])),          // contributors (empty)
            new Response(200, [], json_encode(['nid' => '1234567', 'title' => 'Fix the thing', 'field_issue_category' => 1])), // issue details
            new Response(200, [], json_encode(['list' => []])),           // projectId (null)
        ]);

        $action = new GetMaintainerReleaseNotesAction($client);
        // ref2='HEAD' should not throw even if HEAD is not in the tags list
        $result = $action(self::$gitRepo, self::$tmpDir, '1.0.0', 'HEAD');

        self::assertInstanceOf(MaintainerReleaseNotesResult::class, $result);
        self::assertSame('1.0.0', $result->ref1);
        self::assertSame('HEAD', $result->ref2);
    }

    public function testInvoke(): void
    {
        // MockHandler responses: contributors, issue details, projectId
        $client = $this->makeClientWithMockResponses([
            new Response(200, [], json_encode(['data' => []])),          // contributors (empty)
            new Response(200, [], json_encode(['nid' => '1234567', 'title' => 'Fix the thing', 'field_issue_category' => 1])), // issue details
            new Response(200, [], json_encode(['list' => []])),           // projectId (null → skip change records)
        ]);

        $action = new GetMaintainerReleaseNotesAction($client);
        $result = $action(self::$gitRepo, self::$tmpDir, '1.0.0', '1.1.0');

        self::assertInstanceOf(MaintainerReleaseNotesResult::class, $result);
        self::assertSame('1.0.0', $result->ref1);
        self::assertSame('1.1.0', $result->ref2);
        // Project name is the last component of cwd (temp dir) for non-drupal.org remotes
        self::assertSame(basename(self::$tmpDir), $result->project);
        // NID 1234567 should be extracted from the commit message
        self::assertContains('1234567', $result->nidList);
        // 'Bug' category (field_issue_category=1)
        self::assertArrayHasKey('Bug', $result->categorizedChanges);
        // Contributor extracted from commit message
        self::assertArrayHasKey('testuser', $result->contributors);
        // No change records (projectId returned null)
        self::assertSame([], $result->changeRecords);
    }

    public function testJsonSerialize(): void
    {
        $client = $this->makeClientWithMockResponses([
            new Response(200, [], json_encode(['data' => []])),
            new Response(200, [], json_encode(['nid' => '1234567', 'title' => 'Fix the thing', 'field_issue_category' => 0])),
            new Response(200, [], json_encode(['list' => []])),
        ]);

        $action = new GetMaintainerReleaseNotesAction($client);
        $result = $action(self::$gitRepo, self::$tmpDir, '1.0.0', '1.1.0');

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('1.0.0', $decoded['ref1']);
        self::assertSame('1.1.0', $decoded['ref2']);
        self::assertSame(basename(self::$tmpDir), $decoded['project']);
        self::assertIsArray($decoded['categorized_changes']);
        self::assertIsArray($decoded['contributors']);
        self::assertIsArray($decoded['nid_list']);
        self::assertIsArray($decoded['change_records']);
    }
}
