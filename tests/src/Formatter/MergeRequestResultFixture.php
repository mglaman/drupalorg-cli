<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Formatter;

use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\GitLab\Entity\GitLabNote;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestResult;

/**
 * Builds the MergeRequestResult the formatter tests share.
 */
final class MergeRequestResultFixture
{
    /**
     * @param GitLabIssue[]|null $closesIssues
     *   Defaults to one closing work item.
     */
    public static function make(
        bool $withComments = false,
        ?int $targetNoteId = null,
        ?array $closesIssues = null,
        ?bool $hasConflicts = false,
        ?string $commentsError = null,
    ): MergeRequestResult {
        return new MergeRequestResult(
            iid: 219,
            title: 'Issue #3625000: Fix <b>the</b> widget',
            author: 'contributor',
            state: 'opened',
            draft: true,
            sourceBranch: '3625000-fix-widget',
            targetBranch: '1.x',
            headSha: 'abc123',
            webUrl: 'https://git.drupalcode.org/project/tool/-/merge_requests/219',
            detailedMergeStatus: 'draft_status',
            hasConflicts: $hasConflicts,
            blockingDiscussionsResolved: false,
            pipelineStatus: 'success',
            pipelineUrl: 'https://git.drupalcode.org/project/tool/-/pipelines/99',
            closesIssues: $closesIssues ?? [
                new GitLabIssue(
                    iid: 3625000,
                    title: 'Fix the widget',
                    description: '',
                    state: 'opened',
                    labels: ['state::needsReview', 'category::bug'],
                    createdAt: '2026-10-01T00:00:00Z',
                    updatedAt: '2026-10-02T00:00:00Z',
                    webUrl: 'https://git.drupalcode.org/project/tool/-/work_items/3625000',
                    author: 'reporter',
                    assignees: [],
                ),
            ],
            comments: $withComments ? [
                new GitLabNote(id: 100, body: 'Looks close.', author: 'maintainer', createdAt: '2026-10-20T08:00:00Z', system: false),
                new GitLabNote(
                    id: 3625786,
                    body: 'This needs a <null> check.',
                    author: 'reviewer',
                    createdAt: '2026-10-20T09:00:00Z',
                    system: false,
                    path: 'src/Widget.php',
                    line: 42,
                ),
            ] : [],
            targetNoteId: $targetNoteId,
            commentsError: $commentsError,
        );
    }
}
