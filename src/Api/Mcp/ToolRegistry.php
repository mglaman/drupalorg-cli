<?php

namespace mglaman\DrupalOrg\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use mglaman\DrupalOrg\Action\Issue\GetIssueAction;
use mglaman\DrupalOrg\Action\Issue\GetIssueBranchNameAction;
use mglaman\DrupalOrg\Action\Issue\GetIssueForkAction;
use mglaman\DrupalOrg\Action\Issue\GetIssueLinkAction;
use mglaman\DrupalOrg\Action\Issue\GetLatestIssuePatchAction;
use mglaman\DrupalOrg\Action\Maintainer\GetMaintainerIssuesAction;
use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestDiffAction;
use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestFilesAction;
use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestLogsAction;
use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestStatusAction;
use mglaman\DrupalOrg\Action\MergeRequest\ListMergeRequestsAction;
use mglaman\DrupalOrg\Action\Project\GetProjectIssuesAction;
use mglaman\DrupalOrg\Action\Project\GetProjectReleaseNotesAction;
use mglaman\DrupalOrg\Action\Project\GetProjectReleasesAction;
use mglaman\DrupalOrg\Action\Issue\SearchIssuesAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Enum\MaintainerIssueType;
use mglaman\DrupalOrg\Enum\MergeRequestState;
use mglaman\DrupalOrg\Enum\ProjectIssueCategory;
use mglaman\DrupalOrg\Enum\ProjectIssueType;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;

class ToolRegistry
{
    private const NID_PATTERN = '^\d+$';

    public function __construct(private readonly Client $client)
    {
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'issue_show', description: 'Get details of a Drupal.org issue, optionally including comments.')]
    public function issueShow(
        #[Schema(description: 'The Drupal.org issue node ID.', pattern: self::NID_PATTERN)]
        string $nid,
        #[Schema(description: 'Whether to include issue comments.')]
        bool $withComments = false
    ): mixed {
        return (new GetIssueAction($this->client))($nid, $withComments)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'issue_get_link', description: 'Get the URL for a Drupal.org issue.')]
    public function issueGetLink(
        #[Schema(description: 'The Drupal.org issue node ID.', pattern: self::NID_PATTERN)]
        string $nid
    ): mixed {
        return (new GetIssueLinkAction())($nid)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'issue_get_branch', description: 'Get the Git branch name for an issue.')]
    public function issueGetBranch(
        #[Schema(description: 'The Drupal.org issue node ID.', pattern: self::NID_PATTERN)]
        string $nid
    ): mixed {
        return (new GetIssueBranchNameAction($this->client))($nid)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'issue_get_patch_url', description: 'Get the latest patch URL for an issue.')]
    public function issueGetPatchUrl(
        #[Schema(description: 'The Drupal.org issue node ID.', pattern: self::NID_PATTERN)]
        string $nid
    ): mixed {
        return (new GetLatestIssuePatchAction($this->client))($nid)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'issue_get_fork', description: 'Get GitLab fork info for an issue.')]
    public function issueGetFork(
        #[Schema(description: 'The Drupal.org issue node ID.', pattern: self::NID_PATTERN)]
        string $nid
    ): mixed {
        return (new GetIssueForkAction($this->client, new GitLabClient()))($nid)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'project_get_issues', description: 'Get open issues for a Drupal.org project.')]
    public function projectGetIssues(
        #[Schema(description: "The project machine name (e.g. 'drupal', 'token', 'pathauto').")]
        string $machineName,
        #[Schema(description: 'Filter issues by status type.', enum: ['all', 'rtbc', 'review'])]
        string $type = 'all',
        #[Schema(description: "Core compatibility branch to filter by (e.g. '10.x', '11.x').")]
        string $core = '10.x',
        #[Schema(description: 'Maximum number of issues to return.', minimum: 1, maximum: 100)]
        int $limit = 50,
        #[Schema(description: 'Filter issues by category.', enum: ['bug', 'task', 'feature', 'support', 'plan'])]
        ?string $category = null
    ): mixed {
        $project = $this->client->getProject($machineName);
        if ($project === null) {
            throw new \RuntimeException("Project '$machineName' not found.");
        }
        $issueCategory = $category !== null ? ProjectIssueCategory::from($category) : null;
        return (new GetProjectIssuesAction($this->client))($project, ProjectIssueType::from($type), $core, $limit, $issueCategory)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'issue_search', description: 'Search issues for a Drupal.org project by title keyword.')]
    public function issueSearch(
        #[Schema(description: "The project machine name (e.g. 'drupal', 'token', 'pathauto').")]
        string $machineName,
        #[Schema(description: 'The search text to filter issue titles.')]
        string $query,
        #[Schema(description: 'Maximum number of issues to return.', minimum: 1, maximum: 100)]
        int $limit = 20
    ): mixed {
        $project = $this->client->getProject($machineName);
        if ($project === null) {
            throw new \RuntimeException("Project '$machineName' not found.");
        }
        return (new SearchIssuesAction($this->client))($project, $query, [], $limit)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'project_get_releases', description: 'List releases for a Drupal.org project.')]
    public function projectGetReleases(
        #[Schema(description: "The project machine name (e.g. 'drupal', 'token', 'pathauto').")]
        string $machineName
    ): mixed {
        $project = $this->client->getProject($machineName);
        if ($project === null) {
            throw new \RuntimeException("Project '$machineName' not found.");
        }
        return (new GetProjectReleasesAction($this->client))($project)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'project_get_release_notes', description: 'Get release notes for a project version.')]
    public function projectGetReleaseNotes(
        #[Schema(description: "The project machine name (e.g. 'drupal', 'token', 'pathauto').")]
        string $machineName,
        #[Schema(description: "The release version string (e.g. '2.1.0', '8.x-1.5').")]
        string $version
    ): mixed {
        $project = $this->client->getProject($machineName);
        if ($project === null) {
            throw new \RuntimeException("Project '$machineName' not found.");
        }
        return (new GetProjectReleaseNotesAction($this->client))($project, $version)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'maintainer_get_issues', description: 'Get open issues for a Drupal.org maintainer.')]
    public function maintainerGetIssues(
        #[Schema(description: 'The Drupal.org username of the maintainer.')]
        string $user,
        #[Schema(description: 'Filter issues by status type.', enum: ['any', 'rtbc'])]
        string $type = 'any'
    ): mixed {
        return (new GetMaintainerIssuesAction())($user, MaintainerIssueType::from($type))->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'mr_list', description: 'List merge requests for an issue fork.')]
    public function mrList(
        #[Schema(description: 'The Drupal.org issue node ID.', pattern: self::NID_PATTERN)]
        string $nid,
        #[Schema(description: 'Filter merge requests by state.', enum: ['opened', 'closed', 'merged', 'all'])]
        string $state = 'opened'
    ): mixed {
        return (new ListMergeRequestsAction($this->client, new GitLabClient()))($nid, MergeRequestState::from($state))->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'mr_diff', description: 'Get the unified diff for a merge request.')]
    public function mrDiff(
        #[Schema(description: 'The Drupal.org issue node ID.', pattern: self::NID_PATTERN)]
        string $nid,
        #[Schema(description: 'The merge request internal ID (IID).', minimum: 1)]
        int $mrIid
    ): mixed {
        return (new GetMergeRequestDiffAction($this->client, new GitLabClient()))($nid, $mrIid)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'mr_files', description: 'Get changed files for a merge request.')]
    public function mrFiles(
        #[Schema(description: 'The Drupal.org issue node ID.', pattern: self::NID_PATTERN)]
        string $nid,
        #[Schema(description: 'The merge request internal ID (IID).', minimum: 1)]
        int $mrIid
    ): mixed {
        return (new GetMergeRequestFilesAction($this->client, new GitLabClient()))($nid, $mrIid)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'mr_status', description: 'Get CI pipeline status for a merge request.')]
    public function mrStatus(
        #[Schema(description: 'The Drupal.org issue node ID.', pattern: self::NID_PATTERN)]
        string $nid,
        #[Schema(description: 'The merge request internal ID (IID).', minimum: 1)]
        int $mrIid
    ): mixed {
        return (new GetMergeRequestStatusAction($this->client, new GitLabClient()))($nid, $mrIid)->jsonSerialize();
    }

    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), name: 'mr_logs', description: 'Get failed CI job logs for a merge request.')]
    public function mrLogs(
        #[Schema(description: 'The Drupal.org issue node ID.', pattern: self::NID_PATTERN)]
        string $nid,
        #[Schema(description: 'The merge request internal ID (IID).', minimum: 1)]
        int $mrIid
    ): mixed {
        return (new GetMergeRequestLogsAction($this->client, new GitLabClient()))($nid, $mrIid)->jsonSerialize();
    }
}
