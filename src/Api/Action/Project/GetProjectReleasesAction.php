<?php

namespace mglaman\DrupalOrg\Action\Project;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Result\Project\ProjectReleasesResult;

class GetProjectReleasesAction implements ActionInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    public function __invoke(string $machineName): ProjectReleasesResult
    {
        $project = $this->client->getProject($machineName);
        if ($project === null) {
            throw new \RuntimeException("Project $machineName not found.");
        }
        $releases = $this->client->getProjectReleases($project->nid, [
            'field_release_update_status' => 0,
        ]);
        return new ProjectReleasesResult(
            projectTitle: $project->title,
            projectName: $project->machineName,
            releases: $releases,
        );
    }
}
