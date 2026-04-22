<?php

namespace mglaman\DrupalOrg\Tests\Action\Issue;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use mglaman\DrupalOrg\Action\Issue\GetIssueAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueComment;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetIssueAction::class)]
#[CoversClass(IssueResult::class)]
class GetIssueActionTest extends TestCase
{
    private static function fixture(): \stdClass
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../../fixtures/issue_node.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private static function commentFixture(): string
    {
        return file_get_contents(__DIR__ . '/../../../fixtures/comment_node.json');
    }

    public function testInvoke(): void
    {
        $issueNode = IssueNode::fromStdClass(self::fixture());

        $client = $this->createMock(Client::class);
        $client->method('getNode')->with('3383637')->willReturn($issueNode);

        $action = new GetIssueAction($client);
        $result = $action('3383637');

        self::assertInstanceOf(IssueResult::class, $result);
        self::assertSame('3383637', $result->nid);
        self::assertSame('Schedule transition button size is different for first transition and for second transition', $result->title);
        self::assertSame(6, $result->fieldIssueStatus);
        self::assertSame('drupal', $result->fieldProjectMachineName);
        self::assertSame('11.x-dev', $result->fieldIssueVersion);
        self::assertSame('Claro theme', $result->fieldIssueComponent);
        self::assertSame(200, $result->fieldIssuePriority);
        self::assertSame(1, $result->fieldIssueCategory);
        self::assertSame('3643629', $result->authorId);
        self::assertSame(1693195104, $result->created);
        self::assertSame(1727653295, $result->changed);
        self::assertSame([], $result->comments);
    }

    public function testInvokeWithoutCommentsFlag(): void
    {
        $issueData = self::fixture();
        $commentRef = new \stdClass();
        $commentRef->uri = 'https://www.drupal.org/api-d7/comment/15671234';
        $commentRef->id = '15671234';
        $commentRef->resource = 'comment';
        $issueData->comments = [$commentRef];

        $issueNode = IssueNode::fromStdClass($issueData);

        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn($issueNode);

        $action = new GetIssueAction($client);
        $result = $action('3383637', false);

        self::assertSame([], $result->comments);
    }

    public function testInvokeWithComments(): void
    {
        $issueData = self::fixture();
        $commentRef = new \stdClass();
        $commentRef->uri = 'https://www.drupal.org/api-d7/comment/15671234';
        $commentRef->id = '15671234';
        $commentRef->resource = 'comment';
        $issueData->comments = [$commentRef];

        $issueNode = IssueNode::fromStdClass($issueData);

        $mock = new MockHandler([
            new Response(200, [], self::commentFixture()),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn($issueNode);
        $client->method('getGuzzleClient')->willReturn($guzzleClient);

        $action = new GetIssueAction($client);
        $result = $action('3383637', true);

        self::assertCount(1, $result->comments);
        self::assertInstanceOf(IssueComment::class, $result->comments[0]);
        self::assertSame('15671234', $result->comments[0]->cid);
        self::assertSame('testuser', $result->comments[0]->authorName);
    }

    public function testInvokeWithCommentsFiltersSystemMessages(): void
    {
        $issueData = self::fixture();
        $commentRef = new \stdClass();
        $commentRef->uri = 'https://www.drupal.org/api-d7/comment/99887766';
        $commentRef->id = '99887766';
        $commentRef->resource = 'comment';
        $issueData->comments = [$commentRef];

        $issueNode = IssueNode::fromStdClass($issueData);

        $systemCommentData = [
            'cid' => '99887766',
            'subject' => 'Status: Needs work',
            'comment_body' => ['value' => '<p>System message.</p>', 'format' => '1'],
            'created' => '1700000000',
            'name' => 'System Message',
            'author' => ['uri' => 'https://www.drupal.org/api-d7/user/180064', 'id' => '180064', 'resource' => 'user'],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($systemCommentData)),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn($issueNode);
        $client->method('getGuzzleClient')->willReturn($guzzleClient);

        $action = new GetIssueAction($client);
        $result = $action('3383637', true);

        self::assertSame([], $result->comments);
    }

    public function testJsonSerialize(): void
    {
        $issueNode = IssueNode::fromStdClass(self::fixture());

        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn($issueNode);

        $action = new GetIssueAction($client);
        $result = $action('3383637');

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('3383637', $decoded['nid']);
        self::assertSame(6, $decoded['field_issue_status']);
        self::assertSame('drupal', $decoded['field_project_machine_name']);
        self::assertSame([], $decoded['comments']);
    }
}
