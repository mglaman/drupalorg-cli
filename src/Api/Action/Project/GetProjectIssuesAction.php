<?php

namespace mglaman\DrupalOrg\Action\Project;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\Enum\ProjectIssueCategory;
use mglaman\DrupalOrg\Enum\ProjectIssueType;
use mglaman\DrupalOrg\Request;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;

class GetProjectIssuesAction implements ActionInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    public function __invoke(Project $project, ProjectIssueType $type, int $limit, ?ProjectIssueCategory $category = null): ProjectIssuesResult
    {
        /** @var array<string, mixed> $apiParams */
        $apiParams = [
            'type' => 'project_issue',
            'field_project' => $project->nid,
            'field_issue_status[value]' => [1, 8, 13, 14, 16],
            'sort' => 'field_issue_priority',
            'direction' => 'DESC',
            'limit' => $limit,
        ];

        $apiParams['field_issue_status[value]'] = match ($type) {
            ProjectIssueType::Rtbc => [14],
            ProjectIssueType::Review => [8],
            ProjectIssueType::All => [1, 8, 13, 14, 16],
        };

        if ($category !== null) {
            $apiParams['field_issue_category'] = $category->categoryId();
        }

        $rawIssues = $this->client->requestRaw(new Request('node.json', $apiParams));
        $issueList = (array) ($rawIssues->list ?? []);
        $issues = array_map(
            static fn(\stdClass $issue) => IssueNode::fromStdClass($issue),
            $issueList
        );

        return new ProjectIssuesResult(
            projectTitle: $project->title,
            issues: $issues,
        );
    }
}
