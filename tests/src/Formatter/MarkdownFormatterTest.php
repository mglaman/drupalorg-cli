<?php

namespace mglaman\DrupalOrg\Tests\Formatter;

use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Entity\Release;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;
use mglaman\DrupalOrg\Result\Project\ProjectReleasesResult;
use mglaman\DrupalOrg\Result\ResultInterface;
use mglaman\DrupalOrgCli\Formatter\MarkdownFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownFormatter::class)]
class MarkdownFormatterTest extends TestCase
{
    private static function makeIssueResult(): IssueResult
    {
        return new IssueResult(
            nid: '3383637',
            title: 'Schedule transition button size',
            created: 1693195104,
            changed: 1727653295,
            fieldIssueStatus: 1,
            fieldIssueCategory: 1,
            fieldIssuePriority: 200,
            fieldIssueVersion: '11.x-dev',
            fieldIssueComponent: 'Claro theme',
            fieldProjectMachineName: 'drupal',
            authorId: '3643629',
            bodyValue: '<p>Issue body <strong>content</strong>.</p>',
        );
    }

    private static function makeIssueNode(): IssueNode
    {
        return new IssueNode(
            nid: '1234567',
            title: 'Fix the thing',
            created: 1693195104,
            changed: 1727653295,
            commentCount: 5,
            fieldIssueVersion: '10.x-dev',
            fieldIssueStatus: 14,
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

    private static function makeRelease(): Release
    {
        return new Release(
            nid: '9876',
            fieldReleaseVersion: '10.3.8',
            fieldReleaseVersionExtra: null,
            fieldReleaseShortDescription: 'Security fixes',
            created: 1693195104,
            fieldReleaseProject: 'drupal',
            bodyValue: null,
        );
    }

    public function testIssueResult(): void
    {
        $formatter = new MarkdownFormatter();
        $output = $formatter->format(self::makeIssueResult());

        self::assertStringContainsString('# Schedule transition button size', $output);
        self::assertStringContainsString('**Status:** Active', $output);
        self::assertStringContainsString('**Category:** Bug report', $output);
        self::assertStringContainsString('**Priority:** Normal', $output);
        self::assertStringContainsString('**Project:** drupal', $output);
        self::assertStringContainsString('**Version:** 11.x-dev', $output);
        self::assertStringContainsString('**URL:** https://www.drupal.org/node/3383637', $output);
        self::assertStringContainsString('## Summary', $output);
        self::assertStringContainsString('Issue body content.', $output);
        self::assertStringNotContainsString('<p>', $output);
    }

    public function testProjectIssuesResult(): void
    {
        $result = new ProjectIssuesResult(
            projectTitle: 'Drupal',
            issues: [self::makeIssueNode()],
        );

        $formatter = new MarkdownFormatter();
        $output = $formatter->format($result);

        self::assertStringContainsString('# Drupal', $output);
        self::assertStringContainsString('1234567', $output);
        self::assertStringContainsString('RTBC', $output);
        self::assertStringContainsString('[Fix the thing](https://www.drupal.org/node/1234567)', $output);
    }

    public function testMaintainerIssuesResult(): void
    {
        $result = new MaintainerIssuesResult(
            feedTitle: 'Issues for mglaman',
            items: [
                [
                    'project' => 'commerce',
                    'title' => 'Fix checkout flow',
                    'link' => 'https://www.drupal.org/project/commerce/issues/3001234',
                ],
            ],
        );

        $formatter = new MarkdownFormatter();
        $output = $formatter->format($result);

        self::assertStringContainsString('# Issues for mglaman', $output);
        self::assertStringContainsString('**commerce**', $output);
        self::assertStringContainsString('[Fix checkout flow](https://www.drupal.org/project/commerce/issues/3001234)', $output);
    }

    public function testProjectReleasesResult(): void
    {
        $result = new ProjectReleasesResult(
            projectTitle: 'Drupal',
            projectName: 'drupal',
            releases: [self::makeRelease()],
        );

        $formatter = new MarkdownFormatter();
        $output = $formatter->format($result);

        self::assertStringContainsString('# Drupal', $output);
        self::assertStringContainsString('**10.3.8**', $output);
        self::assertStringContainsString('Security fixes', $output);
    }

    public function testUnsupportedResultTypeThrows(): void
    {
        $result = new class implements ResultInterface {
            public function jsonSerialize(): mixed
            {
                return [];
            }
        };

        $formatter = new MarkdownFormatter();
        $this->expectException(\InvalidArgumentException::class);
        $formatter->format($result);
    }
}
