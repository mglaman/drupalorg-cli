<?php

namespace mglaman\DrupalOrgCli\Formatter;

use mglaman\DrupalOrg\IssueTrait;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;
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
}
