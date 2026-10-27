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
use mglaman\DrupalOrg\Result\Project\ProjectContainsResult;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;
use mglaman\DrupalOrg\Result\Project\ProjectReleasesResult;
use mglaman\DrupalOrg\Result\ResultInterface;
use mglaman\DrupalOrg\Result\Skill\SkillItem;
use mglaman\DrupalOrg\Result\Skill\SkillListResult;
use mglaman\DrupalOrgCli\Formatter\LlmFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\GitLab\Entity\GitLabNote;
use mglaman\DrupalOrg\Result\GitLab\GitLabIssueResult;
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

    public function testProjectContainsResult(): void
    {
        $result = new ProjectContainsResult(
            projectName: 'tool',
            sha: 'd6ef1a7c0ffee',
            title: 'Fix <tool> discovery & cache',
            committedDate: '2025-03-14T09:26:53.000+00:00',
            webUrl: 'https://git.drupalcode.org/project/tool/-/commit/d6ef1a7c0ffee',
            branches: ['1.x'],
            tags: ['1.0.0-beta12', '1.0.0'],
        );

        $output = (new LlmFormatter())->format($result);

        self::assertStringStartsWith('<drupal_context>', $output);
        self::assertStringContainsString('<project>tool</project>', $output);
        self::assertStringContainsString('<sha>d6ef1a7c0ffee</sha>', $output);
        self::assertStringContainsString('<title>Fix &lt;tool&gt; discovery &amp; cache</title>', $output);
        self::assertStringContainsString('<committed_date>2025-03-14T09:26:53.000+00:00</committed_date>', $output);
        self::assertStringContainsString('<url>https://git.drupalcode.org/project/tool/-/commit/d6ef1a7c0ffee</url>', $output);
        self::assertStringContainsString("<branches>\n    <branch>1.x</branch>\n  </branches>", $output);
        self::assertStringContainsString(
            "<tags order=\"oldest_first\">\n    <tag>1.0.0-beta12</tag>\n    <tag>1.0.0</tag>\n  </tags>",
            $output
        );
        self::assertStringEndsWith('</drupal_context>', $output);
    }

    public function testSkillListResult(): void
    {
        $result = new SkillListResult(skills: [
            new SkillItem(name: 'drupalorg-cli', description: 'Full CLI reference & more.', path: '/tmp/SKILL.md'),
        ]);

        $formatter = new LlmFormatter();
        $output = $formatter->format($result);

        self::assertStringStartsWith('<skills>', $output);
        self::assertStringContainsString('<usage>drupalorg skill:get &lt;name&gt;</usage>', $output);
        self::assertStringContainsString('<name>drupalorg-cli</name>', $output);
        self::assertStringContainsString('<description>Full CLI reference &amp; more.</description>', $output);
        self::assertStringEndsWith('</skills>', $output);
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
            exists: true,
            branches: ['3383637-fix-the-thing', 'main'],
        );

        $formatter = new LlmFormatter();
        $output = $formatter->format($result);

        self::assertStringContainsString('<drupal_context>', $output);
        self::assertStringContainsString('<remote_name>drupal-3383637</remote_name>', $output);
        self::assertStringContainsString('<ssh_url>git@git.drupal.org:issue/drupal-3383637.git</ssh_url>', $output);
        self::assertStringContainsString('<https_url>https://git.drupalcode.org/issue/drupal-3383637.git</https_url>', $output);
        self::assertStringContainsString('<gitlab_project_path>issue/drupal-3383637</gitlab_project_path>', $output);
        self::assertStringContainsString('<exists>true</exists>', $output);
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
            description: 'Closes #3383637 & <fixes> it',
            hasConflicts: true,
            blockingDiscussionsResolved: false,
            detailedMergeStatus: 'conflict',
        );

        $result = new MergeRequestListResult(
            projectPath: 'project/drupal',
            mergeRequests: [$mr],
            issueFork: 'issue/drupal-3383637',
        );

        $formatter = new LlmFormatter();
        $output = $formatter->format($result);

        self::assertStringContainsString('<drupal_context>', $output);
        self::assertStringContainsString('<project_path>project/drupal</project_path>', $output);
        self::assertStringContainsString('<issue_fork>issue/drupal-3383637</issue_fork>', $output);
        self::assertStringContainsString('<iid>7</iid>', $output);
        self::assertStringContainsString('<title>Fix &lt;b&gt;broken&lt;/b&gt; &amp; stuff</title>', $output);
        self::assertStringContainsString('<state>opened</state>', $output);
        self::assertStringContainsString('<mergeable>yes</mergeable>', $output);
        self::assertStringContainsString('<author>mglaman</author>', $output);
        self::assertStringContainsString('<url>https://git.drupalcode.org/issue/drupal-3383637/-/merge_requests/7</url>', $output);
        self::assertStringContainsString('<updated_at>2024-01-15T10:00:00Z</updated_at>', $output);
        self::assertStringContainsString('<has_conflicts>yes</has_conflicts>', $output);
        self::assertStringContainsString('<blocking_discussions_resolved>no</blocking_discussions_resolved>', $output);
        self::assertStringContainsString('<detailed_merge_status>conflict</detailed_merge_status>', $output);
        self::assertStringContainsString('<description>Closes #3383637 &amp; &lt;fixes&gt; it</description>', $output);
        // Raw < must not appear inside tag values.
        self::assertStringNotContainsString('<b>', $output);
    }

    public function testMergeRequestResult(): void
    {
        $output = (new LlmFormatter())->format(MergeRequestResultFixture::make());

        self::assertStringContainsString('<drupal_context>', $output);
        self::assertStringContainsString('<iid>219</iid>', $output);
        self::assertStringContainsString('<title>Issue #3625000: Fix &lt;b&gt;the&lt;/b&gt; widget</title>', $output);
        self::assertStringContainsString('<author>contributor</author>', $output);
        self::assertStringContainsString('<state>opened</state>', $output);
        self::assertStringContainsString('<draft>yes</draft>', $output);
        self::assertStringContainsString('<source_branch>3625000-fix-widget</source_branch>', $output);
        self::assertStringContainsString('<target_branch>1.x</target_branch>', $output);
        self::assertStringContainsString('<head_sha>abc123</head_sha>', $output);
        self::assertStringContainsString('<detailed_merge_status>draft_status</detailed_merge_status>', $output);
        self::assertStringContainsString('<has_conflicts>no</has_conflicts>', $output);
        self::assertStringContainsString('<blocking_discussions_resolved>no</blocking_discussions_resolved>', $output);
        self::assertStringContainsString("<head_pipeline>\n    <status>success</status>\n    <url>https://git.drupalcode.org/project/tool/-/pipelines/99</url>", $output);
        self::assertStringContainsString('<iid>3625000</iid>', $output);
        self::assertStringContainsString('<label>state::needsReview</label>', $output);
        self::assertStringContainsString('<url>https://git.drupalcode.org/project/tool/-/work_items/3625000</url>', $output);
        self::assertStringNotContainsString('<comments>', $output);
        self::assertStringNotContainsString('<target_note_id>', $output);
    }

    public function testMergeRequestResultWithoutClosingIssuesHasEmptyElement(): void
    {
        $output = (new LlmFormatter())->format(MergeRequestResultFixture::make(closesIssues: []));

        self::assertStringContainsString("<closes_issues>\n  </closes_issues>", $output);
    }

    public function testMergeRequestResultMarksTheLinkedNote(): void
    {
        $output = (new LlmFormatter())->format(MergeRequestResultFixture::make(withComments: true, targetNoteId: 3625786));

        self::assertStringContainsString('<target_note_id>3625786</target_note_id>', $output);
        self::assertStringContainsString("<comment>\n      <id>100</id>", $output);
        self::assertStringContainsString("<comment target=\"true\">\n      <id>3625786</id>", $output);
        self::assertSame(1, substr_count($output, 'target="true"'));
        self::assertStringContainsString('<path>src/Widget.php</path>', $output);
        self::assertStringContainsString('<line>42</line>', $output);
        self::assertStringContainsString('<body><![CDATA[This needs a <null> check.]]></body>', $output);
    }

    public function testMergeRequestResultWithUncheckedConflicts(): void
    {
        $output = (new LlmFormatter())->format(MergeRequestResultFixture::make(hasConflicts: null));

        self::assertStringContainsString('<has_conflicts>unknown</has_conflicts>', $output);
    }

    public function testMergeRequestResultFlagsAMissingLinkedNote(): void
    {
        $output = (new LlmFormatter())->format(MergeRequestResultFixture::make(withComments: true, targetNoteId: 999));

        self::assertStringContainsString('<target_note_id missing="true">999</target_note_id>', $output);
        self::assertStringNotContainsString('target="true"', $output);
    }

    public function testMergeRequestResultReportsUnavailableComments(): void
    {
        $output = (new LlmFormatter())->format(MergeRequestResultFixture::make(targetNoteId: 3625786, commentsError: 'Needs a <token>.'));

        self::assertStringContainsString('<target_note_id>3625786</target_note_id>', $output);
        self::assertStringContainsString('<comments_error>Needs a &lt;token&gt;.</comments_error>', $output);
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

    private static function makeGitLabIssue(): GitLabIssue
    {
        return new GitLabIssue(
            iid: 3586157,
            title: 'Example AI context issue',
            description: 'Body',
            state: 'opened',
            labels: [],
            createdAt: '2025-01-01T00:00:00Z',
            updatedAt: '2025-01-02T00:00:00Z',
            webUrl: 'https://git.drupalcode.org/project/ai_context/-/work_items/3586157',
            author: 'reporter',
            assignees: [],
        );
    }

    private static function makeGitLabIssueResult(): GitLabIssueResult
    {
        return new GitLabIssueResult(self::makeGitLabIssue(), [
            new GitLabNote(id: 2, body: 'Reviewed <b>the</b> approach.', author: 'reviewer', createdAt: '2025-01-01T02:00:00Z', system: false),
        ]);
    }

    public function testGitLabIssueWithComments(): void
    {
        $formatter = new LlmFormatter();
        $output = $formatter->format(self::makeGitLabIssueResult());

        self::assertStringContainsString('<gitlab_context>', $output);
        self::assertStringContainsString('<comments>', $output);
        self::assertStringContainsString('<number>1</number>', $output);
        self::assertStringContainsString('<author>reviewer</author>', $output);
        self::assertStringContainsString('<created>2025-01-01T02:00:00Z</created>', $output);
        self::assertStringContainsString('<![CDATA[Reviewed <b>the</b> approach.]]>', $output);
        self::assertStringContainsString('</comments>', $output);
    }

    public function testGitLabIssueWithoutCommentsOmitsCommentsElement(): void
    {
        $formatter = new LlmFormatter();
        $output = $formatter->format(new GitLabIssueResult(self::makeGitLabIssue()));

        self::assertStringNotContainsString('<comments>', $output);
    }
}
