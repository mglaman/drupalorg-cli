<?php

namespace mglaman\DrupalOrg\Tests\Formatter;

use mglaman\DrupalOrg\Entity\IssueComment;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Entity\Release;
use mglaman\DrupalOrg\Result\Issue\IssueForkResult;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestItem;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestListResult;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;
use mglaman\DrupalOrg\Result\Project\ProjectReleasesResult;
use mglaman\DrupalOrg\Result\ResultInterface;
use mglaman\DrupalOrgCli\Formatter\LlmFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LlmFormatter::class)]
class LlmFormatterTest extends TestCase
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
        $formatter = new LlmFormatter();
        $output = $formatter->format(self::makeIssueResult());

        self::assertStringContainsString('<drupal_context>', $output);
        self::assertStringContainsString('</drupal_context>', $output);
        self::assertStringContainsString('<issue_id>3383637</issue_id>', $output);
        self::assertStringContainsString('<title>Schedule transition button size</title>', $output);
        self::assertStringContainsString('<status>Active</status>', $output);
        self::assertStringContainsString('<category>Bug report</category>', $output);
        self::assertStringContainsString('<priority>Normal</priority>', $output);
        self::assertStringContainsString('<project>drupal</project>', $output);
        self::assertStringContainsString('<version>11.x-dev</version>', $output);
        self::assertStringContainsString('<component>Claro theme</component>', $output);
        self::assertStringContainsString('<created>', $output);
        self::assertStringContainsString('<updated>', $output);
        self::assertStringContainsString('<description>', $output);
        self::assertStringNotContainsString('<p>', $output);
        self::assertStringContainsString('Issue body content.', $output);
    }

    public function testIssueResultWithComments(): void
    {
        $comment = new IssueComment(
            cid: '15671234',
            bodyValue: '<p>LGTM <strong>with</strong> nits.</p>',
            created: 1700000000,
            authorId: '99999',
            authorName: 'reviewer',
        );
        $result = new IssueResult(
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
            bodyValue: '<p>Issue body.</p>',
            comments: [$comment],
        );

        $formatter = new LlmFormatter();
        $output = $formatter->format($result);

        self::assertStringContainsString('<comments>', $output);
        self::assertStringContainsString('<comment>', $output);
        self::assertStringContainsString('<number>1</number>', $output);
        self::assertStringContainsString('<author>reviewer</author>', $output);
        self::assertStringContainsString('<created>', $output);
        self::assertStringContainsString('LGTM', $output);
        self::assertStringContainsString('<![CDATA[', $output);
        self::assertStringContainsString('<strong>', $output);
        self::assertStringContainsString('</comments>', $output);
    }

    public function testIssueResultWithoutCommentsHasNoCommentsBlock(): void
    {
        $formatter = new LlmFormatter();
        $output = $formatter->format(self::makeIssueResult());

        self::assertStringNotContainsString('<comments>', $output);
    }

    public function testProjectIssuesResult(): void
    {
        $result = new ProjectIssuesResult(
            projectTitle: 'Drupal',
            issues: [self::makeIssueNode()],
        );

        $formatter = new LlmFormatter();
        $output = $formatter->format($result);

        self::assertStringContainsString('<drupal_context>', $output);
        self::assertStringContainsString('</drupal_context>', $output);
        self::assertStringContainsString('<project>Drupal</project>', $output);
        self::assertStringContainsString('<items>', $output);
        self::assertStringContainsString('<item>', $output);
        self::assertStringContainsString('<nid>1234567</nid>', $output);
        self::assertStringContainsString('<title>Fix the thing</title>', $output);
        self::assertStringContainsString('<status>RTBC</status>', $output);
        self::assertStringContainsString('<url>https://www.drupal.org/node/1234567</url>', $output);
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

        $formatter = new LlmFormatter();
        $output = $formatter->format($result);

        self::assertStringContainsString('<drupal_context>', $output);
        self::assertStringContainsString('<feed_title>Issues for mglaman</feed_title>', $output);
        self::assertStringContainsString('<items>', $output);
        self::assertStringContainsString('<project>commerce</project>', $output);
        self::assertStringContainsString('<title>Fix checkout flow</title>', $output);
        self::assertStringContainsString('<link>https://www.drupal.org/project/commerce/issues/3001234</link>', $output);
    }

    public function testProjectReleasesResult(): void
    {
        $result = new ProjectReleasesResult(
            projectTitle: 'Drupal',
            projectName: 'drupal',
            releases: [self::makeRelease()],
        );

        $formatter = new LlmFormatter();
        $output = $formatter->format($result);

        self::assertStringContainsString('<drupal_context>', $output);
        self::assertStringContainsString('<project>Drupal</project>', $output);
        self::assertStringContainsString('<items>', $output);
        self::assertStringContainsString('<version>10.3.8</version>', $output);
        self::assertStringContainsString('<date>', $output);
        self::assertStringContainsString('<description>Security fixes</description>', $output);
    }

    public function testUnsupportedResultTypeThrows(): void
    {
        $result = new class implements ResultInterface {
            public function jsonSerialize(): mixed
            {
                return [];
            }
        };

        $formatter = new LlmFormatter();
        $this->expectException(\InvalidArgumentException::class);
        $formatter->format($result);
    }

    public function testIssueForkResult(): void
    {
        $result = new IssueForkResult(
            remoteName: 'drupal-3383637',
            sshUrl: 'git@git.drupal.org:issue/drupal-3383637.git',
            httpsUrl: 'https://git.drupalcode.org/issue/drupal-3383637.git',
            gitLabProjectPath: 'issue/drupal-3383637',
            branches: ['3383637-fix-the-thing', 'main'],
        );

        $formatter = new LlmFormatter();
        $output = $formatter->format($result);

        self::assertStringContainsString('<drupal_context>', $output);
        self::assertStringContainsString('<remote_name>drupal-3383637</remote_name>', $output);
        self::assertStringContainsString('<ssh_url>git@git.drupal.org:issue/drupal-3383637.git</ssh_url>', $output);
        self::assertStringContainsString('<https_url>https://git.drupalcode.org/issue/drupal-3383637.git</https_url>', $output);
        self::assertStringContainsString('<gitlab_project_path>issue/drupal-3383637</gitlab_project_path>', $output);
        self::assertStringContainsString('<branch>3383637-fix-the-thing</branch>', $output);
        self::assertStringContainsString('<branch>main</branch>', $output);
    }

    public function testMergeRequestListResult(): void
    {
        $mr = new MergeRequestItem(
            iid: 7,
            title: 'Fix <b>broken</b> & stuff',
            sourceBranch: '3383637-fix-the-thing',
            targetBranch: '11.x',
            state: 'opened',
            webUrl: 'https://git.drupalcode.org/issue/drupal-3383637/-/merge_requests/7',
            isMergeable: true,
            author: 'mglaman',
            updatedAt: '2024-01-15T10:00:00Z',
        );

        $result = new MergeRequestListResult(
            projectPath: 'issue/drupal-3383637',
            mergeRequests: [$mr],
        );

        $formatter = new LlmFormatter();
        $output = $formatter->format($result);

        self::assertStringContainsString('<drupal_context>', $output);
        self::assertStringContainsString('<project_path>issue/drupal-3383637</project_path>', $output);
        self::assertStringContainsString('<iid>7</iid>', $output);
        self::assertStringContainsString('<title>Fix &lt;b&gt;broken&lt;/b&gt; &amp; stuff</title>', $output);
        self::assertStringContainsString('<state>opened</state>', $output);
        self::assertStringContainsString('<mergeable>yes</mergeable>', $output);
        self::assertStringContainsString('<author>mglaman</author>', $output);
        self::assertStringContainsString('<url>https://git.drupalcode.org/issue/drupal-3383637/-/merge_requests/7</url>', $output);
        self::assertStringContainsString('<updated_at>2024-01-15T10:00:00Z</updated_at>', $output);
        // Raw < must not appear inside tag values.
        self::assertStringNotContainsString('<b>', $output);
    }

    public function testXmlEscapingInTitle(): void
    {
        $result = new IssueResult(
            nid: '123',
            title: 'Fix <script> & "quotes"',
            created: 1693195104,
            changed: 1727653295,
            fieldIssueStatus: 1,
            fieldIssueCategory: 1,
            fieldIssuePriority: 200,
            fieldIssueVersion: '10.x-dev',
            fieldIssueComponent: 'Base',
            fieldProjectMachineName: 'drupal',
            authorId: null,
            bodyValue: null,
        );

        $formatter = new LlmFormatter();
        $output = $formatter->format($result);

        self::assertStringNotContainsString('<script>', $output);
        self::assertStringContainsString('&lt;script&gt;', $output);
        self::assertStringContainsString('&amp;', $output);
    }
}
