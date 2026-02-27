<?php

namespace mglaman\DrupalOrg\Tests\Action\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestDiffAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestDiffResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetMergeRequestDiffAction::class)]
#[CoversClass(MergeRequestDiffResult::class)]
class GetMergeRequestDiffActionTest extends TestCase
{
    private static function makeIssueNode(): IssueNode
    {
        return new IssueNode(
            nid: '3383637',
            title: 'Test issue',
            created: 1693195104,
            changed: 1727653295,
            commentCount: 0,
            fieldIssueVersion: '11.x-dev',
            fieldIssueStatus: 1,
            fieldIssueCategory: 1,
            fieldIssuePriority: 200,
            fieldIssueComponent: 'Base system',
            fieldProjectId: '3060',
            fieldProjectMachineName: 'drupal',
            bodyValue: null,
            authorId: null,
            fieldIssueFiles: [],
            comments: [],
        );
    }

    private static function makeProject(): \stdClass
    {
        $project = new \stdClass();
        $project->id = 12345;
        return $project;
    }

    private static function makeMr(): \stdClass
    {
        $mr = new \stdClass();
        $mr->source_branch = '3383637-fix-the-bug';
        $mr->target_branch = '11.x';
        return $mr;
    }

    private static function makeFileDiff(string $diff): \stdClass
    {
        $d = new \stdClass();
        $d->diff = $diff;
        return $d;
    }

    private function makeAction(): GetMergeRequestDiffAction
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequest')->willReturn(self::makeMr());

        return new GetMergeRequestDiffAction($client, $gitLabClient);
    }

    public function testUnifiedDiffConcatenation(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequest')->willReturn(self::makeMr());
        $gitLabClient->method('getMergeRequestDiffs')->willReturn([
            self::makeFileDiff("--- a/foo.php\n+++ b/foo.php\n@@ -1 +1 @@\n-old\n+new\n"),
            self::makeFileDiff("--- a/bar.php\n+++ b/bar.php\n@@ -1 +1 @@\n-x\n+y\n"),
        ]);

        $action = new GetMergeRequestDiffAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertSame(7, $result->iid);
        self::assertSame('3383637-fix-the-bug', $result->sourceBranch);
        self::assertSame('11.x', $result->targetBranch);
        self::assertStringContainsString('foo.php', $result->diff);
        self::assertStringContainsString('bar.php', $result->diff);
    }

    public function testEmptyDiffsAreSkipped(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequest')->willReturn(self::makeMr());
        $gitLabClient->method('getMergeRequestDiffs')->willReturn([
            self::makeFileDiff(''),
            self::makeFileDiff("--- a/foo.php\n+++ b/foo.php\n@@ -1 +1 @@\n-old\n+new\n"),
            self::makeFileDiff(''),
        ]);

        $action = new GetMergeRequestDiffAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertSame("--- a/foo.php\n+++ b/foo.php\n@@ -1 +1 @@\n-old\n+new\n", $result->diff);
    }

    public function testMissingTrailingNewlineIsAdded(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequest')->willReturn(self::makeMr());
        $gitLabClient->method('getMergeRequestDiffs')->willReturn([
            self::makeFileDiff("--- a/foo.php\n+++ b/foo.php\n-old\n+new"),   // no trailing newline
            self::makeFileDiff("--- a/bar.php\n+++ b/bar.php\n-x\n+y\n"),
        ]);

        $action = new GetMergeRequestDiffAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        // The two diffs must be separated by a newline boundary.
        self::assertStringContainsString("+new\n--- a/bar.php", $result->diff);
    }
}
