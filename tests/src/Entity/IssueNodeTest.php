<?php

namespace mglaman\DrupalOrg\Tests\Entity;

use mglaman\DrupalOrg\Entity\IssueFile;
use mglaman\DrupalOrg\Entity\IssueNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IssueNode::class)]
#[CoversClass(IssueFile::class)]
class IssueNodeTest extends TestCase
{
    private static function fixture(): \stdClass
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/issue_node.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function testFromStdClass(): void
    {
        $issue = IssueNode::fromStdClass(self::fixture());

        self::assertSame('3383637', $issue->nid);
        self::assertSame('Schedule transition button size is different for first transition and for second transition', $issue->title);
        self::assertSame(1693195104, $issue->created);
        self::assertSame(1727653295, $issue->changed);
        self::assertSame(18, $issue->commentCount);
        self::assertSame('11.x-dev', $issue->fieldIssueVersion);
        self::assertSame(6, $issue->fieldIssueStatus);
        self::assertSame(1, $issue->fieldIssueCategory);
        self::assertSame(200, $issue->fieldIssuePriority);
        self::assertSame('Claro theme', $issue->fieldIssueComponent);
        self::assertSame('3060', $issue->fieldProjectId);
        self::assertSame('drupal', $issue->fieldProjectMachineName);
        self::assertStringContainsString('Schedule transition', $issue->bodyValue);
        self::assertSame('3643629', $issue->authorId);
        self::assertCount(1, $issue->fieldIssueFiles);
        self::assertCount(1, $issue->comments);

        $issueFile = $issue->fieldIssueFiles[0];
        self::assertInstanceOf(IssueFile::class, $issueFile);
        self::assertTrue($issueFile->display);
        self::assertSame('3786488', $issueFile->fileId);
        self::assertSame(5, $issueFile->cid);

        self::assertSame('15671234', $issue->comments[0]->id);
    }

    public function testFromStdClassWithEmptyData(): void
    {
        $issue = IssueNode::fromStdClass(new \stdClass());

        self::assertSame('', $issue->nid);
        self::assertSame('', $issue->title);
        self::assertSame(0, $issue->created);
        self::assertSame(0, $issue->changed);
        self::assertSame(0, $issue->commentCount);
        self::assertSame('', $issue->fieldIssueVersion);
        self::assertSame(0, $issue->fieldIssueStatus);
        self::assertSame('', $issue->fieldProjectId);
        self::assertSame('', $issue->fieldProjectMachineName);
        self::assertNull($issue->bodyValue);
        self::assertNull($issue->authorId);
        self::assertSame([], $issue->fieldIssueFiles);
        self::assertSame([], $issue->comments);
    }

    public function testBuildCleanTitle(): void
    {
        $issue = IssueNode::fromStdClass(self::fixture());
        // Title: "Schedule transition button size is different for first transition and for second transition"
        // After regex + lowercase + truncate to 20 chars + strip leading/trailing underscores:
        self::assertSame('schedule_transition', $issue->buildCleanTitle());
    }

    public function testBuildBranchName(): void
    {
        $issue = IssueNode::fromStdClass(self::fixture());
        self::assertSame('3383637-schedule_transition', $issue->buildBranchName());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function versionBranchProvider(): array
    {
        return [
            // Traditional Drupal.org branch format.
            'traditional-minor' => ['8.x-1.0', '1234', '8.x-1.x'],
            'traditional-dev' => ['8.x-1.x-dev', '1234', '8.x-1.x'],
            'traditional-rc' => ['8.x-1.0-rc1', '1234', '8.x-1.x'],
            'traditional-major2' => ['8.x-2.0', '1234', '8.x-2.x'],
            // Semantic versioning branch format.
            'semver-patch-wildcard' => ['1.0.0-x', '1234', '1.0.x'],
            'semver-dev' => ['1.0.x-dev', '1234', '1.0.x'],
            'semver-patch' => ['1.0.1', '1234', '1.0.x'],
            'semver-alpha' => ['2.0.0-alpha1', '1234', '2.0.x'],
            // Drupal core (project ID 3060).
            'drupal-core' => ['11.x-dev', '3060', '11.x-'],
        ];
    }

    #[DataProvider('versionBranchProvider')]
    public function testBuildIssueVersionBranch(
        string $version,
        string $projectId,
        string $expectedBranch
    ): void {
        $issue = new IssueNode(
            nid: '12345',
            title: 'Test issue',
            created: 0,
            changed: 0,
            commentCount: 0,
            fieldIssueVersion: $version,
            fieldIssueStatus: 1,
            fieldIssueCategory: 1,
            fieldIssuePriority: 200,
            fieldIssueComponent: 'Test',
            fieldProjectId: $projectId,
            fieldProjectMachineName: 'test_module',
            bodyValue: null,
            authorId: null,
            fieldIssueFiles: [],
            comments: [],
        );
        self::assertSame($expectedBranch, $issue->buildIssueVersionBranch());
    }
}
