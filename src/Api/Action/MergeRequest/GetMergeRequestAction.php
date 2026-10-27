<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\MergeRequest;

use GuzzleHttp\Exception\ClientException;
use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\GitLab\Entity\GitLabNote;
use mglaman\DrupalOrg\GitLab\MergeRequestRef;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestResult;

class GetMergeRequestAction extends AbstractMergeRequestAction
{
    /**
     * GitLab checks mergeability lazily. Until it has, has_conflicts reads
     * false whether or not the merge request conflicts.
     */
    private const UNCHECKED_MERGE_STATUSES = ['unchecked', 'checking', 'preparing'];

    /**
     * A ref with a "#note_<id>" fragment always fetches comments, and keeps
     * that note even when it is a drupalbot note.
     */
    public function __invoke(
        string $nid,
        int $mrIid,
        ?MergeRequestRef $ref = null,
        bool $withComments = false,
        bool $includeBotComments = false,
    ): MergeRequestResult {
        [$projectId] = $ref !== null ? $this->resolveFromRef($ref) : $this->resolveGitLabProject($nid);

        // Asks GitLab to check mergeability now, so the next run reports
        // conflicts. Anonymous and low-role requests ignore it.
        $mr = $this->gitLabClient->getMergeRequest($projectId, $mrIid, ['with_merge_status_recheck' => 'true']);
        $closesIssues = array_map(
            static fn(\stdClass $issue) => GitLabIssue::fromStdClass($issue),
            $this->gitLabClient->getMergeRequestClosesIssues($projectId, $mrIid)
        );

        $targetNoteId = $ref?->noteId;
        $comments = [];
        $commentsError = null;
        if ($withComments || $targetNoteId !== null) {
            try {
                $comments = $this->getComments($projectId, $mrIid, $includeBotComments, $targetNoteId);
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() !== 401) {
                    throw $e;
                }
                $commentsError = 'GitLab requires a token to read merge request comments. Set DRUPALORG_GITLAB_TOKEN, or store one with glab for git.drupalcode.org.';
            }
        }

        $pipeline = isset($mr->head_pipeline) && $mr->head_pipeline instanceof \stdClass ? $mr->head_pipeline : null;
        $detailedMergeStatus = (string) ($mr->detailed_merge_status ?? '');

        return new MergeRequestResult(
            iid: (int) $mr->iid,
            title: (string) ($mr->title ?? ''),
            author: (string) ($mr->author->username ?? ''),
            state: (string) ($mr->state ?? ''),
            draft: (bool) ($mr->draft ?? false),
            sourceBranch: (string) ($mr->source_branch ?? ''),
            targetBranch: (string) ($mr->target_branch ?? ''),
            headSha: (string) ($mr->sha ?? ''),
            webUrl: (string) ($mr->web_url ?? ''),
            detailedMergeStatus: $detailedMergeStatus,
            hasConflicts: in_array($detailedMergeStatus, self::UNCHECKED_MERGE_STATUSES, true)
                ? null
                : (bool) ($mr->has_conflicts ?? false),
            blockingDiscussionsResolved: (bool) ($mr->blocking_discussions_resolved ?? true),
            pipelineStatus: $pipeline !== null ? (string) ($pipeline->status ?? '') : null,
            pipelineUrl: $pipeline !== null ? (string) ($pipeline->web_url ?? '') : null,
            closesIssues: $closesIssues,
            comments: $comments,
            targetNoteId: $targetNoteId,
            commentsError: $commentsError,
        );
    }

    /**
     * @return GitLabNote[]
     */
    private function getComments(int $projectId, int $mrIid, bool $includeBotComments, ?int $targetNoteId): array
    {
        $comments = [];
        foreach ($this->gitLabClient->getMergeRequestNotes($projectId, $mrIid) as $noteData) {
            $note = GitLabNote::fromStdClass($noteData);
            if ($note->id === $targetNoteId) {
                $comments[] = $note;
                continue;
            }
            if ($note->system) {
                continue;
            }
            if (!$includeBotComments && $note->isFromBot()) {
                continue;
            }
            $comments[] = $note;
        }
        return $comments;
    }
}
