<?php

namespace mglaman\DrupalOrgCli\Formatter;

use mglaman\DrupalOrg\IssueTrait;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;
use mglaman\DrupalOrg\Result\Project\ProjectReleasesResult;

class LlmFormatter extends AbstractFormatter
{
    use IssueTrait;

    protected function formatIssue(IssueResult $result): string
    {
        $nid = $result->nid;
        $title = $this->xmlEscape($result->title);
        $status = $this->getIssueStatusLabel($result->fieldIssueStatus);
        $category = $this->getIssueCategoryLabel($result->fieldIssueCategory);
        $priority = $this->getIssuePriorityLabel($result->fieldIssuePriority);
        $project = $this->xmlEscape($result->fieldProjectMachineName);
        $version = $this->xmlEscape($result->fieldIssueVersion);
        $component = $this->xmlEscape($result->fieldIssueComponent);
        $created = $this->toIso8601($result->created);
        $updated = $this->toIso8601($result->changed);
        $description = $this->stripAndEscape($result->bodyValue ?? '');

        return <<<XML
<drupal_context>
  <issue_id>{$nid}</issue_id>
  <title>{$title}</title>
  <status>{$status}</status>
  <category>{$category}</category>
  <priority>{$priority}</priority>
  <project>{$project}</project>
  <version>{$version}</version>
  <component>{$component}</component>
  <created>{$created}</created>
  <updated>{$updated}</updated>
  <description>{$description}</description>
</drupal_context>
XML;
    }

    protected function formatProjectIssues(ProjectIssuesResult $result): string
    {
        $projectTitle = $this->xmlEscape($result->projectTitle);
        $items = '';
        foreach ($result->issues as $issue) {
            $title = $this->xmlEscape($issue->title);
            $status = $this->getIssueStatusLabel($issue->fieldIssueStatus);
            $items .= "    <item>\n";
            $items .= "      <nid>{$issue->nid}</nid>\n";
            $items .= "      <title>{$title}</title>\n";
            $items .= "      <status>{$status}</status>\n";
            $items .= "      <url>https://www.drupal.org/node/{$issue->nid}</url>\n";
            $items .= "    </item>\n";
        }
        return "<drupal_context>\n  <project>{$projectTitle}</project>\n  <items>\n{$items}  </items>\n</drupal_context>";
    }

    protected function formatMaintainerIssues(MaintainerIssuesResult $result): string
    {
        $feedTitle = $this->xmlEscape($result->feedTitle);
        $items = '';
        foreach ($result->items as $item) {
            $project = $this->xmlEscape($item['project']);
            $title = $this->xmlEscape($item['title']);
            $link = $item['link'];
            $items .= "    <item>\n";
            $items .= "      <project>{$project}</project>\n";
            $items .= "      <title>{$title}</title>\n";
            $items .= "      <link>{$link}</link>\n";
            $items .= "    </item>\n";
        }
        return "<drupal_context>\n  <feed_title>{$feedTitle}</feed_title>\n  <items>\n{$items}  </items>\n</drupal_context>";
    }

    protected function formatProjectReleases(ProjectReleasesResult $result): string
    {
        $projectTitle = $this->xmlEscape($result->projectTitle);
        $items = '';
        foreach ($result->releases as $release) {
            $version = $this->xmlEscape($release->fieldReleaseVersion);
            $date = $this->toIso8601($release->created);
            $description = $this->xmlEscape($release->fieldReleaseShortDescription ?? '');
            $items .= "    <item>\n";
            $items .= "      <version>{$version}</version>\n";
            $items .= "      <date>{$date}</date>\n";
            $items .= "      <description>{$description}</description>\n";
            $items .= "    </item>\n";
        }
        return "<drupal_context>\n  <project>{$projectTitle}</project>\n  <items>\n{$items}  </items>\n</drupal_context>";
    }

    private function toIso8601(int $timestamp): string
    {
        return (new \DateTimeImmutable())->setTimestamp($timestamp)->format(\DateTimeInterface::ATOM);
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function stripAndEscape(string $value): string
    {
        return $this->xmlEscape(strip_tags($value));
    }
}
