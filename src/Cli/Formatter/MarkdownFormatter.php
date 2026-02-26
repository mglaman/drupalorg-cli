<?php

namespace mglaman\DrupalOrgCli\Formatter;

use mglaman\DrupalOrg\IssueTrait;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;
use mglaman\DrupalOrg\Result\Project\ProjectReleasesResult;
use mglaman\DrupalOrg\Result\ResultInterface;

class MarkdownFormatter implements FormatterInterface
{
    use IssueTrait;

    public function format(ResultInterface $result): string
    {
        return match (true) {
            $result instanceof IssueResult => $this->formatIssue($result),
            $result instanceof ProjectIssuesResult => $this->formatProjectIssues($result),
            $result instanceof MaintainerIssuesResult => $this->formatMaintainerIssues($result),
            $result instanceof ProjectReleasesResult => $this->formatProjectReleases($result),
            default => throw new \InvalidArgumentException(
                sprintf('Unsupported result type: %s', get_class($result))
            ),
        };
    }

    private function formatIssue(IssueResult $result): string
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

    private function formatProjectIssues(ProjectIssuesResult $result): string
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

    private function formatMaintainerIssues(MaintainerIssuesResult $result): string
    {
        $lines = [];
        $lines[] = "# {$result->feedTitle}";
        $lines[] = '';
        foreach ($result->items as $item) {
            $lines[] = "- **{$item['project']}** — [{$item['title']}]({$item['link']})";
        }
        return implode("\n", $lines);
    }

    private function formatProjectReleases(ProjectReleasesResult $result): string
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
