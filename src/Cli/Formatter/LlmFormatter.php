<?php

namespace mglaman\DrupalOrgCli\Formatter;

use mglaman\DrupalOrg\IssueTrait;
use mglaman\DrupalOrg\Result\Issue\IssueForkResult;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestListResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestStatusResult;
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

    protected function formatIssueFork(IssueForkResult $result): string
    {
        $remoteName = $this->xmlEscape($result->remoteName);
        $sshUrl = $this->xmlEscape($result->sshUrl);
        $httpsUrl = $this->xmlEscape($result->httpsUrl);
        $gitLabPath = $this->xmlEscape($result->gitLabProjectPath);

        $branchItems = '';
        foreach ($result->branches as $branch) {
            $branchItems .= '    <branch>' . $this->xmlEscape($branch) . "</branch>\n";
        }

        return <<<XML
<drupal_context>
  <remote_name>{$remoteName}</remote_name>
  <ssh_url>{$sshUrl}</ssh_url>
  <https_url>{$httpsUrl}</https_url>
  <gitlab_project_path>{$gitLabPath}</gitlab_project_path>
  <branches>
{$branchItems}  </branches>
</drupal_context>
XML;
    }

    protected function formatMergeRequestList(MergeRequestListResult $result): string
    {
        $projectPath = $this->xmlEscape($result->projectPath);
        $items = '';
        foreach ($result->mergeRequests as $mr) {
            $title = $this->xmlEscape($mr->title);
            $sourceBranch = $this->xmlEscape($mr->sourceBranch);
            $targetBranch = $this->xmlEscape($mr->targetBranch);
            $author = $this->xmlEscape($mr->author);
            $mergeable = $mr->isMergeable ? 'yes' : 'no';
            $items .= "    <merge_request>\n";
            $items .= "      <iid>{$mr->iid}</iid>\n";
            $items .= "      <title>{$title}</title>\n";
            $items .= "      <source_branch>{$sourceBranch}</source_branch>\n";
            $items .= "      <target_branch>{$targetBranch}</target_branch>\n";
            $state = $this->xmlEscape($mr->state);
            $updatedAt = $this->xmlEscape($mr->updatedAt);
            $items .= "      <state>{$state}</state>\n";
            $items .= "      <mergeable>{$mergeable}</mergeable>\n";
            $items .= "      <author>{$author}</author>\n";
            $items .= "      <url>" . $this->xmlEscape($mr->webUrl) . "</url>\n";
            $items .= "      <updated_at>{$updatedAt}</updated_at>\n";
            $items .= "    </merge_request>\n";
        }
        return "<drupal_context>\n  <project_path>{$projectPath}</project_path>\n  <merge_requests>\n{$items}  </merge_requests>\n</drupal_context>";
    }

    protected function formatMergeRequestStatus(MergeRequestStatusResult $result): string
    {
        $status = $this->xmlEscape($result->status);
        $pipelineId = $result->pipelineId !== null ? (string) $result->pipelineId : '';
        $pipelineUrl = $this->xmlEscape($result->pipelineUrl ?? '');

        return <<<XML
<drupal_context>
  <merge_request_iid>{$result->iid}</merge_request_iid>
  <pipeline_id>{$pipelineId}</pipeline_id>
  <status>{$status}</status>
  <pipeline_url>{$pipelineUrl}</pipeline_url>
</drupal_context>
XML;
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
