<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Result\MergeRequest;

use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\GitLab\Entity\GitLabNote;
use mglaman\DrupalOrg\Result\ResultInterface;

class MergeRequestResult implements ResultInterface
{
    /**
     * @param string|null $pipelineStatus
     *   The head pipeline status as GitLab reports it, or null when the merge
     *   request has no pipeline.
     * @param GitLabIssue[] $closesIssues
     *   The work items GitLab closes when this merge request merges.
     * @param GitLabNote[] $comments
     * @param int|null $targetNoteId
     *   The note a "#note_<id>" link pointed at.
     */
    public function __construct(
        public readonly int $iid,
        public readonly string $title,
        public readonly string $author,
        public readonly string $state,
        public readonly bool $draft,
        public readonly string $sourceBranch,
        public readonly string $targetBranch,
        public readonly string $headSha,
        public readonly string $webUrl,
        public readonly string $detailedMergeStatus,
        public readonly bool $hasConflicts,
        public readonly bool $blockingDiscussionsResolved,
        public readonly ?string $pipelineStatus,
        public readonly ?string $pipelineUrl,
        public readonly array $closesIssues,
        public readonly array $comments = [],
        public readonly ?int $targetNoteId = null,
    ) {
    }

    public function isTargetNote(GitLabNote $note): bool
    {
        return $this->targetNoteId !== null && $note->id === $this->targetNoteId;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'iid' => $this->iid,
            'title' => $this->title,
            'author' => $this->author,
            'state' => $this->state,
            'draft' => $this->draft,
            'source_branch' => $this->sourceBranch,
            'target_branch' => $this->targetBranch,
            'head_sha' => $this->headSha,
            'web_url' => $this->webUrl,
            'detailed_merge_status' => $this->detailedMergeStatus,
            'has_conflicts' => $this->hasConflicts,
            'blocking_discussions_resolved' => $this->blockingDiscussionsResolved,
            'head_pipeline' => $this->pipelineStatus === null ? null : [
                'status' => $this->pipelineStatus,
                'web_url' => $this->pipelineUrl,
            ],
            'closes_issues' => array_map(
                static fn(GitLabIssue $issue) => [
                    'iid' => $issue->iid,
                    'title' => $issue->title,
                    'state' => $issue->state,
                    'labels' => $issue->labels,
                    'web_url' => $issue->webUrl,
                ],
                $this->closesIssues
            ),
            'target_note_id' => $this->targetNoteId,
            'comments' => array_map(
                fn(GitLabNote $note) => [...(array) $note->jsonSerialize(), 'is_target' => $this->isTargetNote($note)],
                $this->comments
            ),
        ];
    }
}
