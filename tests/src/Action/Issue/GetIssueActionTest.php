<?php

namespace mglaman\DrupalOrg\Tests\Action\Issue;

use mglaman\DrupalOrg\Action\Issue\GetIssueAction;
use mglaman\DrupalOrg\Client;
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
    }
}
