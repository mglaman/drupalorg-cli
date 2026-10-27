<?php

namespace mglaman\DrupalOrgCli\Formatter;

use mglaman\DrupalOrg\IssueTrait;
use mglaman\DrupalOrg\Result\GitLab\GitLabIssueResult;
use mglaman\DrupalOrg\Result\GitLab\GitLabIssuesResult;
use mglaman\DrupalOrg\Result\Issue\IssueForkResult;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
use mglaman\DrupalOrg\Result\Issue\SlashCommandResult;
use mglaman\DrupalOrg\Result\Issue\WorkItemCommentResult;
use mglaman\DrupalOrg\Result\Issue\WorkItemLinkResult;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestDiffResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestFilesResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestListResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestStatusResult;
use mglaman\DrupalOrg\Result\Issue\IssueSearchResult;
use mglaman\DrupalOrg\Result\Project\ProjectContainsResult;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;
use mglaman\DrupalOrg\Result\Project\ProjectReleasesResult;
use mglaman\DrupalOrg\Result\Skill\SkillListResult;

class MarkdownFormatter extends AbstractFormatter
{
    use IssueTrait;

    protected function formatIssue(IssueResult $result): string
    {
        $lines = [];
        $lines[] = "# {$result->title}";
        $lines[] = '';
        $lines[] = "- **Status:** {$this->getIssueStatusLabel($result->fieldIssueStatus)}";
        $lines[] = "- **Category:** {$this->getIssueCategoryLabel($result->fieldIssueCategory)}";
        $lines[] = "- **Priority:** {$this->getIssuePriorityLabel($result->fieldIssuePriority)}";
        $lines[] = "- **Project:** {$result->fieldProjectMachineName}";
        $lines[] = "- **Version:** {$result->fieldIssueVersion}";
        $lines[] = "- **Component:** {$result->fieldIssueComponent}";
        $lines[] = '- **Created:** ' . date('c', $result->created);
        $lines[] = '- **Updated:** ' . date('c', $result->changed);
        $lines[] = "- **URL:** https://www.drupal.org/node/{$result->nid}";
        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = strip_tags($result->bodyValue ?? '');
        if ($result->comments !== []) {
            $lines[] = '';
            $lines[] = '## Comments';
            foreach ($result->comments as $index => $comment) {
                $lines[] = '';
                $lines[] = sprintf(
                    '### Comment #%d — %s (%s)',
                    $index + 1,
                    $comment->authorName,
                    date('c', $comment->created)
                );
                $lines[] = '';
                $lines[] = strip_tags($comment->bodyValue ?? '');
            }
        }
        return implode("\n", $lines);
    }

    protected function formatIssueSearch(IssueSearchResult $result): string
    {
        $lines = [];
        $heading = $result->projectTitle ?? 'Issue Search Results';
        $lines[] = "# {$heading}";
        $lines[] = '';
        foreach ($result->issues as $issue) {
            $status = $this->getIssueStatusLabel($issue->fieldIssueStatus);
            $url = "https://www.drupal.org/node/{$issue->nid}";
            $lines[] = "- **{$issue->nid}** [{$status}] [{$issue->title}]({$url})";
        }
        return implode("\n", $lines);
    }

    protected function formatProjectIssues(ProjectIssuesResult $result): string
    {
        $lines = [];
        $lines[] = "# {$result->projectTitle}";
        $lines[] = '';
        foreach ($result->issues as $issue) {
            $status = $this->getIssueStatusLabel($issue->fieldIssueStatus);
            $url = "https://www.drupal.org/node/{$issue->nid}";
            $lines[] = "- **{$issue->nid}** [{$status}] [{$issue->title}]({$url})";
        }
        return implode("\n", $lines);
    }

    protected function formatMaintainerIssues(MaintainerIssuesResult $result): string
    {
        $lines = [];
        $lines[] = "# {$result->feedTitle}";
        $lines[] = '';
        foreach ($result->items as $item) {
            $lines[] = "- **{$item['project']}** — [{$item['title']}]({$item['link']})";
        }
        return implode("\n", $lines);
    }

    protected function formatProjectReleases(ProjectReleasesResult $result): string
    {
        $lines = [];
        $lines[] = "# {$result->projectTitle}";
        $lines[] = '';
        foreach ($result->releases as $release) {
            $date = date('c', $release->created);
            $description = $release->fieldReleaseShortDescription ?? '';
            $lines[] = "- **{$release->fieldReleaseVersion}** ({$date}) — {$description}";
        }
        return implode("\n", $lines);
    }

    protected function formatProjectContains(ProjectContainsResult $result): string
    {
        $lines = [];
        $lines[] = "# {$result->title}";
        $lines[] = '';
        $lines[] = "- **Project:** {$result->projectName}";
        $lines[] = "- **Commit:** [{$result->sha}]({$result->webUrl})";
        $lines[] = "- **Committed:** {$result->committedDate}";
        $lines[] = '';
        $lines[] = '## Branches';
        $lines[] = '';
        $lines[] = $result->branches === [] ? 'None' : '- ' . implode("\n- ", $result->branches);
        $lines[] = '';
        $lines[] = '## Tags (oldest first)';
        $lines[] = '';
        $lines[] = $result->tags === [] ? 'None' : '- ' . implode("\n- ", $result->tags);
        return implode("\n", $lines);
    }

    protected function formatIssueFork(IssueForkResult $result): string
    {
        $lines = [];
        $lines[] = "# Issue Fork: {$result->remoteName}";
        $lines[] = '';
        $lines[] = "- **Remote name:** {$result->remoteName}";
        $lines[] = "- **SSH URL:** {$result->sshUrl}";
        $lines[] = "- **HTTPS URL:** {$result->httpsUrl}";
        $lines[] = "- **GitLab path:** {$result->gitLabProjectPath}";
        $lines[] = '- **Exists:** ' . ($result->exists ? 'yes' : 'no');
        if ($result->branches !== []) {
            $lines[] = '';
            $lines[] = '## Branches';
            $lines[] = '';
            foreach ($result->branches as $branch) {
                $lines[] = "- {$branch}";
            }
        }
        return implode("\n", $lines);
    }

    protected function formatMergeRequestList(MergeRequestListResult $result): string
    {
        $lines = [];
        $lines[] = "# Merge Requests: {$result->projectPath}";
        $lines[] = '';
        if ($result->issueFork !== null) {
            $lines[] = "Scoped to issue fork `{$result->issueFork}`.";
            $lines[] = '';
        }
        foreach ($result->mergeRequests as $mr) {
            $mergeable = $mr->isMergeable ? ' ✓' : '';
            $lines[] = "- **!{$mr->iid}** [{$mr->state}{$mergeable}] [{$mr->title}]({$mr->webUrl})";
            $lines[] = "  - Branch: `{$mr->sourceBranch}` → `{$mr->targetBranch}`";
            $lines[] = "  - Author: {$mr->author} | Updated: {$mr->updatedAt}";
            $conflicts = $mr->hasConflicts ? 'yes' : 'no';
            $discussions = $mr->blockingDiscussionsResolved ? 'resolved' : 'unresolved';
            $mergeStatus = $mr->detailedMergeStatus !== '' ? $mr->detailedMergeStatus : 'unknown';
            $lines[] = "  - Conflicts: {$conflicts} | Discussions: {$discussions} | Merge status: {$mergeStatus}";
            if ($mr->description !== '') {
                $lines[] = '';
                foreach (explode("\n", $mr->description) as $descriptionLine) {
                    $lines[] = '    ' . $descriptionLine;
                }
            }
        }
        return implode("\n", $lines);
    }

    protected function formatMergeRequestStatus(MergeRequestStatusResult $result): string
    {
        $lines = [];
        $lines[] = "# MR !{$result->iid} Pipeline Status";
        $lines[] = '';
        $lines[] = "- **Status:** {$result->status}";
        if ($result->pipelineId !== null) {
            $lines[] = "- **Pipeline ID:** {$result->pipelineId}";
        }
        if ($result->pipelineUrl !== null && $result->pipelineUrl !== '') {
            $lines[] = "- **Pipeline URL:** {$result->pipelineUrl}";
        }
        return implode("\n", $lines);
    }

    protected function formatMergeRequest(MergeRequestResult $result): string
    {
        $lines = [];
        $lines[] = "# MR !{$result->iid}: {$result->title}";
        $lines[] = '';
        $draft = $result->draft ? ' (draft)' : '';
        $lines[] = "- **State:** {$result->state}{$draft}";
        $lines[] = "- **Author:** {$result->author}";
        $lines[] = "- **Branch:** `{$result->sourceBranch}` → `{$result->targetBranch}`";
        $lines[] = "- **Head SHA:** `{$result->headSha}`";
        $mergeStatus = $result->detailedMergeStatus !== '' ? $result->detailedMergeStatus : 'unknown';
        $conflicts = match ($result->hasConflicts) {
            true => 'yes',
            false => 'no',
            null => 'unknown (not checked yet, run again)',
        };
        $discussions = $result->blockingDiscussionsResolved ? 'resolved' : 'unresolved';
        $lines[] = "- **Merge status:** {$mergeStatus} | Conflicts: {$conflicts} | Discussions: {$discussions}";
        if ($result->pipelineStatus === null) {
            $lines[] = '- **Pipeline:** none';
        } else {
            $lines[] = "- **Pipeline:** {$result->pipelineStatus} {$result->pipelineUrl}";
        }
        $lines[] = "- **URL:** {$result->webUrl}";
        $lines[] = '';
        $lines[] = '## Closes issues';
        $lines[] = '';
        if ($result->closesIssues === []) {
            $lines[] = 'None. Merging this MR does not close any work item.';
        }
        foreach ($result->closesIssues as $issue) {
            $labels = $issue->labels !== [] ? ' — ' . implode(', ', $issue->labels) : '';
            $lines[] = "- [#{$issue->iid}]({$issue->webUrl}) [{$issue->state}] {$issue->title}{$labels}";
        }
        if ($result->commentsError !== null) {
            $lines[] = '';
            $lines[] = "> **Comments unavailable:** {$result->commentsError}";
        }
        if ($result->isTargetNoteMissing()) {
            $lines[] = '';
            $lines[] = "> **Linked note {$result->targetNoteId} not found** on this merge request.";
        }
        if ($result->comments !== []) {
            $lines[] = '';
            $lines[] = '## Comments';
            foreach ($result->comments as $comment) {
                $target = $result->isTargetNote($comment) ? ' ← linked note' : '';
                $lines[] = '';
                $lines[] = sprintf('### Note %d — %s (%s)%s', $comment->id, $comment->author, $comment->createdAt, $target);
                if ($comment->path !== null) {
                    $location = $comment->line !== null ? "{$comment->path}:{$comment->line}" : $comment->path;
                    $lines[] = '';
                    $lines[] = "On `{$location}`";
                }
                $lines[] = '';
                $lines[] = $comment->body;
            }
        }
        return implode("\n", $lines);
    }

    protected function formatMergeRequestFiles(MergeRequestFilesResult $result): string
    {
        $lines = [];
        $lines[] = "# MR !{$result->iid} Changed Files";
        $lines[] = '';
        foreach ($result->files as $file) {
            $path = (string) $file['path'];
            $annotations = [];
            if ((bool) $file['new_file']) {
                $annotations[] = '*(new)*';
            }
            if ((bool) $file['deleted_file']) {
                $annotations[] = '*(deleted)*';
            }
            if ((bool) $file['renamed_file']) {
                $annotations[] = '*(renamed)*';
            }
            $suffix = $annotations !== [] ? ' ' . implode(' ', $annotations) : '';
            $lines[] = "- {$path}{$suffix}";
        }
        return implode("\n", $lines);
    }

    protected function formatMergeRequestDiff(MergeRequestDiffResult $result): string
    {
        $lines = [];
        $lines[] = "# MR !{$result->iid} Diff (`{$result->sourceBranch}` → `{$result->targetBranch}`)";
        $lines[] = '';
        $lines[] = '```diff';
        $lines[] = $result->diff;
        $lines[] = '```';
        return implode("\n", $lines);
    }

    protected function formatGitLabIssue(GitLabIssueResult $result): string
    {
        $issue = $result->issue;
        $lines = [];
        $lines[] = "# {$issue->title}";
        $lines[] = '';
        $lines[] = "- **State:** {$issue->state}";
        $lines[] = "- **Author:** {$issue->author}";
        if ($issue->assignees !== []) {
            $lines[] = '- **Assignees:** ' . implode(', ', $issue->assignees);
        }
        if ($issue->labels !== []) {
            $lines[] = '- **Labels:** ' . implode(', ', $issue->labels);
        }
        $lines[] = "- **Created:** {$issue->createdAt}";
        $lines[] = "- **Updated:** {$issue->updatedAt}";
        $lines[] = "- **URL:** {$issue->webUrl}";
        $lines[] = '';
        $lines[] = '## Description';
        $lines[] = '';
        $lines[] = $issue->description;
        if ($result->comments !== []) {
            $lines[] = '';
            $lines[] = '## Comments';
            foreach ($result->comments as $index => $comment) {
                $lines[] = '';
                $lines[] = sprintf('### Comment #%d — %s (%s)', $index + 1, $comment->author, $comment->createdAt);
                $lines[] = '';
                $lines[] = $comment->body;
            }
        }
        return implode("\n", $lines);
    }

    protected function formatGitLabIssues(GitLabIssuesResult $result): string
    {
        $lines = [];
        $lines[] = "# {$result->projectMachineName}";
        $lines[] = '';
        foreach ($result->issues as $issue) {
            $labels = $issue->labels !== [] ? ' [' . implode(', ', $issue->labels) . ']' : '';
            $lines[] = "- **#{$issue->iid}** [{$issue->state}]{$labels} [{$issue->title}]({$issue->webUrl})";
        }
        return implode("\n", $lines);
    }

    protected function formatSlashCommand(SlashCommandResult $result): string
    {
        return sprintf(
            "Posted `%s` on [%s#%d](%s) (note #%d).",
            $result->command,
            $result->projectPath,
            $result->issueIid,
            $result->workItemUrl(),
            $result->noteId,
        );
    }

    protected function formatWorkItemComment(WorkItemCommentResult $result): string
    {
        return sprintf(
            "Posted [note #%d](%s) on %s#%d.",
            $result->noteId,
            $result->noteUrl(),
            $result->projectPath,
            $result->issueIid,
        );
    }

    protected function formatWorkItemLink(WorkItemLinkResult $result): string
    {
        return sprintf(
            "%s [%s#%d](%s) `%s` [%s#%d](%s).",
            $result->alreadyLinked ? 'Already linked' : 'Linked',
            $result->sourceProjectPath,
            $result->sourceIid,
            $result->sourceUrl(),
            $result->linkType,
            $result->targetProjectPath,
            $result->targetIid,
            $result->targetUrl(),
        );
    }

    protected function formatSkillList(SkillListResult $result): string
    {
        $lines = [];
        $lines[] = '# Available skills';
        $lines[] = '';
        $lines[] = '| Skill | Description |';
        $lines[] = '|---|---|';
        foreach ($result->skills as $skill) {
            $lines[] = "| `{$skill->name}` | {$skill->description} |";
        }
        $lines[] = '';
        $lines[] = 'Run `drupalorg skill:get <name>` to read a skill.';
        return implode("\n", $lines);
    }
}
