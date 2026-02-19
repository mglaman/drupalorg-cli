<?php

namespace mglaman\DrupalOrg\Tests\Entity;

use mglaman\DrupalOrg\Entity\IssueFile;
use mglaman\DrupalOrg\Entity\IssueNode;
use PHPUnit\Framework\Attributes\CoversClass;
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
}
