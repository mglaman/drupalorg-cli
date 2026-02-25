<?php

namespace mglaman\DrupalOrg\Action\Project;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\Request;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;

class GetProjectIssuesAction implements ActionInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    public function __invoke(Project $project, string $type, string $core, int $limit): ProjectIssuesResult
    {
        $rawReleases = $this->client->requestRaw(new Request('node.json', [
            'field_release_project' => $project->nid,
            'type' => 'project_release',
            'sort' => 'nid',
            'direction' => 'DESC',
            'limit' => 100,
        ]));
        $releaseList = (array) ($rawReleases->list ?? []);

        /** @var array<string, mixed> $apiParams */
        $apiParams = [
            'type' => 'project_issue',
            'field_project' => $project->nid,
            'field_issue_status[value]' => [1, 8, 13, 14, 16],
            'sort' => 'field_issue_priority',
            'direction' => 'DESC',
            'limit' => $limit,
        ];

        switch ($type) {
            case 'rtbc':
                $apiParams['field_issue_status[value]'] = [14];
                break;
            case 'review':
                $apiParams['field_issue_status[value]'] = [8];
                break;
            default:
                $apiParams['field_issue_status[value]'] = [1, 8, 13, 14, 16];
        }

        foreach ($releaseList as $release) {
            if (strpos($release->field_release_version, $core) === 0) {
                $apiParams['field_issue_version']['value'][] = $release->field_release_version;
            }
        }

        $rawIssues = $this->client->requestRaw(new Request('node.json', $apiParams));
        $issueList = (array) ($rawIssues->list ?? []);

        return new ProjectIssuesResult(
            projectTitle: $project->title,
            issues: $issueList,
        );
    }
}
