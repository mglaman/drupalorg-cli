<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use GuzzleHttp\Exception\ClientException;
use mglaman\DrupalOrg\Enum\MergeRequestState;
use mglaman\DrupalOrg\GitLab\MergeRequestRef;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestItem;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestListResult;

class ListMergeRequestsAction extends AbstractMergeRequestAction
{
    /**
     * Lists merge requests for an issue fork, or for a whole project.
     *
     * Merge requests belong to the target project on GitLab, so listing them
     * on the fork returns nothing. Instead this lists the parent project and
     * filters by the fork's project ID. A missing fork yields an empty list.
     *
     * @param string|null $projectMachineName
     *   Skips the Drupal.org node lookup when the caller already knows the
     *   project, such as from a WorkItemRef.
     */
    public function __invoke(
        string $nid,
        MergeRequestState $state = MergeRequestState::Opened,
        ?MergeRequestRef $ref = null,
        ?string $projectMachineName = null,
    ): MergeRequestListResult {
        $params = ['per_page' => 100];
        if ($state !== MergeRequestState::All) {
            $params['state'] = $state->value;
        }

        if ($ref !== null) {
            [$projectId, $projectPath] = $this->resolveFromRef($ref);
            return new MergeRequestListResult(
                projectPath: $projectPath,
                mergeRequests: $this->fetch($projectId, $params),
            );
        }

        if ($projectMachineName === null) {
            $projectMachineName = $this->client->getNode($nid)->fieldProjectMachineName;
        }
        $projectPath = 'project/' . $projectMachineName;
        $issueForkPath = 'issue/' . $projectMachineName . '-' . $nid;

        $forkId = $this->findProjectId($issueForkPath);
        if ($forkId === null) {
            return new MergeRequestListResult(
                projectPath: $projectPath,
                mergeRequests: [],
                issueFork: $issueForkPath,
            );
        }

        $project = $this->gitLabClient->getProject($projectPath);
        $params['source_project_id'] = $forkId;

        return new MergeRequestListResult(
            projectPath: $projectPath,
            mergeRequests: $this->fetch((int) $project->id, $params),
            issueFork: $issueForkPath,
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return MergeRequestItem[]
     */
    private function fetch(int $projectId, array $params): array
    {
        return array_map(
            static fn(\stdClass $mr) => MergeRequestItem::fromStdClass($mr),
            $this->gitLabClient->getMergeRequests($projectId, $params)
        );
    }

    private function findProjectId(string $path): ?int
    {
        try {
            return (int) $this->gitLabClient->getProject($path)->id;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
}
