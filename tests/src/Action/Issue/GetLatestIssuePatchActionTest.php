<?php

namespace mglaman\DrupalOrg\Tests\Action\Issue;

use mglaman\DrupalOrg\Action\Issue\GetLatestIssuePatchAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\File;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Result\Issue\IssuePatchResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetLatestIssuePatchAction::class)]
#[CoversClass(IssuePatchResult::class)]
class GetLatestIssuePatchActionTest extends TestCase
{
    private static function issueFixture(): \stdClass
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
        $issueNode = IssueNode::fromStdClass(self::issueFixture());
        $patchFile = new File(
            fid: '3786488',
            name: 'schedule_transition.patch',
            url: 'https://www.drupal.org/files/issues/2024/schedule_transition.patch',
        );

        $client = $this->createMock(Client::class);
        $client->method('getNode')->with('3383637')->willReturn($issueNode);
        $client->method('getFile')->with('3786488')->willReturn($patchFile);

        $action = new GetLatestIssuePatchAction($client);
        $result = $action('3383637');

        self::assertInstanceOf(IssuePatchResult::class, $result);
        self::assertSame('https://www.drupal.org/files/issues/2024/schedule_transition.patch', $result->patchUrl);
        self::assertSame('schedule_transition.patch', $result->patchFileName);
        self::assertSame('3383637-schedule_transition', $result->branchName);
        self::assertSame('11.x-', $result->issueVersionBranch);
    }

    public function testInvokeThrowsWhenNoPatchFile(): void
    {
        $issueFixture = self::issueFixture();
        // Remove all issue files so no patch is found.
        $issueFixture->field_issue_files = [];
        $issueNode = IssueNode::fromStdClass($issueFixture);

        $client = $this->createMock(Client::class);
        $client->method('getNode')->with('3383637')->willReturn($issueNode);

        $action = new GetLatestIssuePatchAction($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No patch file found for issue 3383637');
        $action('3383637');
    }

    public function testJsonSerialize(): void
    {
        $issueNode = IssueNode::fromStdClass(self::issueFixture());
        $patchFile = new File(
            fid: '3786488',
            name: 'schedule_transition.patch',
            url: 'https://www.drupal.org/files/issues/2024/schedule_transition.patch',
        );

        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn($issueNode);
        $client->method('getFile')->willReturn($patchFile);

        $action = new GetLatestIssuePatchAction($client);
        $result = $action('3383637');

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('https://www.drupal.org/files/issues/2024/schedule_transition.patch', $decoded['patch_url']);
        self::assertSame('schedule_transition.patch', $decoded['patch_file_name']);
        self::assertSame('3383637-schedule_transition', $decoded['branch_name']);
        self::assertSame('11.x-', $decoded['issue_version_branch']);
    }
}
