<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\Enum\ProjectIssueCategory;
use mglaman\DrupalOrg\Enum\ProjectIssueType;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\Result\GitLab\GitLabIssuesResult;

class ListGitLabIssuesAction
{
    public function __construct(private readonly GitLabClient $gitLabClient)
    {
    }

    public function __invoke(
        string $projectMachineName,
        string $state = 'opened',
        int $limit = 25,
        ProjectIssueType $type = ProjectIssueType::All,
        ?ProjectIssueCategory $category = null,
    ): GitLabIssuesResult {
        $params = [
            'state' => $state,
            'per_page' => $limit,
            'order_by' => 'created_at',
            'sort' => 'desc',
        ];

        $labels = array_filter([$type->gitLabLabel(), $category?->gitLabLabel()]);
        if ($labels !== []) {
            $params['labels'] = implode(',', $labels);
        }

        $data = $this->gitLabClient->getIssues('project/' . $projectMachineName, $params);
        $issues = array_map(
            static fn(\stdClass $item) => GitLabIssue::fromStdClass($item),
            $data
        );

        return new GitLabIssuesResult(
            projectMachineName: $projectMachineName,
            issues: $issues,
        );
    }
}
