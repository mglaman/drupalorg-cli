<?php

namespace mglaman\DrupalOrgCli\Formatter;

use mglaman\DrupalOrg\IssueTrait;
use mglaman\DrupalOrg\Result\Issue\IssueForkResult;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestDiffResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestFilesResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestListResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestStatusResult;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;
use mglaman\DrupalOrg\Result\Project\ProjectReleasesResult;

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

    protected function formatIssueFork(IssueForkResult $result): string
    {
        $lines = [];
        $lines[] = "# Issue Fork: {$result->remoteName}";
        $lines[] = '';
        $lines[] = "- **Remote name:** {$result->remoteName}";
        $lines[] = "- **SSH URL:** {$result->sshUrl}";
        $lines[] = "- **HTTPS URL:** {$result->httpsUrl}";
        $lines[] = "- **GitLab path:** {$result->gitLabProjectPath}";
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
        foreach ($result->mergeRequests as $mr) {
            $mergeable = $mr->isMergeable ? ' ✓' : '';
            $lines[] = "- **!{$mr->iid}** [{$mr->state}{$mergeable}] [{$mr->title}]({$mr->webUrl})";
            $lines[] = "  - Branch: `{$mr->sourceBranch}` → `{$mr->targetBranch}`";
            $lines[] = "  - Author: {$mr->author} | Updated: {$mr->updatedAt}";
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
}
