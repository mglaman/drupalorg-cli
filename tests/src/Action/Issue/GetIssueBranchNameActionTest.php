<?php

namespace mglaman\DrupalOrg\Tests\Action\Issue;

use mglaman\DrupalOrg\Action\Issue\GetIssueBranchNameAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrg\MigratedIssueException;
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

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::never())->method('getIssue');

        $action = new GetIssueBranchNameAction($client, $gitLabClient);
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

        $action = new GetIssueBranchNameAction($client, $this->createMock(GitLabClient::class));
        $result = $action('3383637');

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('3383637-schedule_transition', $decoded['branch_name']);
        self::assertSame('11.x-', $decoded['issue_version_branch']);
    }

    /**
     * @param string[] $labels
     */
    private static function makeWorkItem(array $labels): \stdClass
    {
        $issue = new \stdClass();
        $issue->iid = 3617735;
        $issue->title = 'Fix JS on add form and remove jQuery dependency';
        $issue->labels = $labels;
        return $issue;
    }

    public function testWorkItemRefUsesVersionLabel(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('getNode');

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssue')
            ->with('project/restrict_route_by_ip', 3617735)
            ->willReturn(self::makeWorkItem(['state::fixed', 'v2.0.x-dev']));
        $gitLabClient->expects(self::never())->method('getProject');

        $action = new GetIssueBranchNameAction($client, $gitLabClient);
        $result = $action('3617735', new WorkItemRef('project/restrict_route_by_ip', 3617735));

        self::assertSame('3617735-fix_js_on_add_form_a', $result->branchName);
        self::assertSame('2.0.x', $result->issueVersionBranch);
    }

    public function testWorkItemWithoutVersionLabelUsesDefaultBranch(): void
    {
        $project = new \stdClass();
        $project->default_branch = '1.0.x';

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssue')->willReturn(self::makeWorkItem(['state::needsReview']));
        $gitLabClient->method('getProject')->with('project/ai_context')->willReturn($project);

        $action = new GetIssueBranchNameAction($this->createMock(Client::class), $gitLabClient);
        $result = $action('3617735', new WorkItemRef('project/ai_context', 3617735));

        self::assertSame('1.0.x', $result->issueVersionBranch);
    }

    public function testBareNidFollowsMigratedIssueToGitLab(): void
    {
        $ref = new WorkItemRef('project/restrict_route_by_ip', 3617735);
        $client = $this->createMock(Client::class);
        $client->method('getNode')->with('3617735')->willThrowException(new MigratedIssueException(
            '3617735',
            $ref,
            'https://git.drupalcode.org/project/restrict_route_by_ip/-/work_items/3617735'
        ));

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssue')
            ->with('project/restrict_route_by_ip', 3617735)
            ->willReturn(self::makeWorkItem(['v2.0.x-dev']));

        $action = new GetIssueBranchNameAction($client, $gitLabClient);
        $result = $action('3617735');

        self::assertSame('3617735-fix_js_on_add_form_a', $result->branchName);
        self::assertSame('2.0.x', $result->issueVersionBranch);
    }
}
