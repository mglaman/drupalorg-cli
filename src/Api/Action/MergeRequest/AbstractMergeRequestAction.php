<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\MergeRequestRef;

abstract class AbstractMergeRequestAction implements ActionInterface
{
    public function __construct(
        protected readonly Client $client,
        protected readonly GitLabClient $gitLabClient,
    ) {
    }

    /**
     * @return array{0: int, 1: string}
     */
    protected function resolveGitLabProject(string $nid): array
    {
        $issue = $this->client->getNode($nid);
        $projectPath = 'project/' . $issue->fieldProjectMachineName;
        $project = $this->gitLabClient->getProject($projectPath);
        return [(int) $project->id, $projectPath];
    }

    /**
     * @return array{0: int, 1: string}
     */
    protected function resolveFromRef(MergeRequestRef $ref): array
    {
        $project = $this->gitLabClient->getProject($ref->projectPath);
        return [(int) $project->id, $ref->projectPath];
    }
}
