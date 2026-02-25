<?php

namespace mglaman\DrupalOrg\Tests\Action\Issue;

use mglaman\DrupalOrg\Action\Issue\GetIssueBranchNameAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Result\Issue\IssueBranchResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetIssueBranchNameAction::class)]
#[CoversClass(IssueBranchResult::class)]
class GetIssueBranchNameActionTest extends TestCase
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

        $action = new GetIssueBranchNameAction($client);
        $result = $action('3383637');

        self::assertInstanceOf(IssueBranchResult::class, $result);
        self::assertSame('3383637-schedule_transition', $result->branchName);
        // fieldProjectId=3060 (Drupal core), version='11.x-dev' → substr(0,5)='11.x-'
        self::assertSame('11.x-', $result->issueVersionBranch);
    }

    public function testJsonSerialize(): void
    {
        $issueNode = IssueNode::fromStdClass(self::fixture());

        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn($issueNode);

        $action = new GetIssueBranchNameAction($client);
        $result = $action('3383637');

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('3383637-schedule_transition', $decoded['branch_name']);
        self::assertSame('11.x-', $decoded['issue_version_branch']);
    }
}
