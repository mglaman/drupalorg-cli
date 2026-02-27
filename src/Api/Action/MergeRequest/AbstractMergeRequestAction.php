<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;

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
        $remoteName = $issue->fieldProjectMachineName . '-' . $nid;
        $projectPath = 'issue/' . $remoteName;
        $project = $this->gitLabClient->getProject($projectPath);
        return [(int) $project->id, $projectPath];
    }
}
