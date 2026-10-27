<?php

namespace mglaman\DrupalOrg\Tests\Formatter;

use mglaman\DrupalOrg\Result\Issue\IssueResult;
use mglaman\DrupalOrgCli\Formatter\JsonFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonFormatter::class)]
class JsonFormatterTest extends TestCase
{
    private static function makeIssueResult(): IssueResult
    {
        return new IssueResult(
            nid: '3383637',
            title: 'Test issue title',
            created: 1693195104,
            changed: 1727653295,
            fieldIssueStatus: 1,
            fieldIssueCategory: 1,
            fieldIssuePriority: 200,
            fieldIssueVersion: '11.x-dev',
            fieldIssueComponent: 'Claro theme',
            fieldProjectMachineName: 'drupal',
            authorId: '3643629',
            bodyValue: '<p>Issue body content.</p>',
        );
    }

    public function testFormatProducesValidJson(): void
    {
        $formatter = new JsonFormatter();
        $output = $formatter->format(self::makeIssueResult());

        self::assertJson($output);
    }

    public function testFormatContainsExpectedFields(): void
    {
        $formatter = new JsonFormatter();
        $output = $formatter->format(self::makeIssueResult());

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertSame('3383637', $decoded['nid']);
        self::assertSame('Test issue title', $decoded['title']);
        self::assertSame('drupal', $decoded['field_project_machine_name']);
        self::assertSame(1, $decoded['field_issue_status']);
        self::assertSame(200, $decoded['field_issue_priority']);
    }

    public function testMergeRequestResultMarksTheLinkedNote(): void
    {
        $output = (new JsonFormatter())->format(MergeRequestResultFixture::make(withComments: true, targetNoteId: 3625786));

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertSame(219, $decoded['iid']);
        self::assertTrue($decoded['draft']);
        self::assertSame(['status' => 'success', 'web_url' => 'https://git.drupalcode.org/project/tool/-/pipelines/99'], $decoded['head_pipeline']);
        self::assertSame(3625000, $decoded['closes_issues'][0]['iid']);
        self::assertSame(['state::needsReview', 'category::bug'], $decoded['closes_issues'][0]['labels']);
        self::assertSame(3625786, $decoded['target_note_id']);
        self::assertFalse($decoded['comments'][0]['is_target']);
        self::assertTrue($decoded['comments'][1]['is_target']);
        self::assertSame('src/Widget.php', $decoded['comments'][1]['path']);
        self::assertSame(42, $decoded['comments'][1]['line']);
    }

    public function testFormatIsPrettyPrinted(): void
    {
        $formatter = new JsonFormatter();
        $output = $formatter->format(self::makeIssueResult());

        self::assertStringContainsString("\n", $output);
    }
}
