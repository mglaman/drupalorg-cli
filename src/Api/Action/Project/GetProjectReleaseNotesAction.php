<?php

namespace mglaman\DrupalOrg\Action\Project;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Request;
use mglaman\DrupalOrg\Result\Project\ProjectReleaseNotesResult;

class GetProjectReleaseNotesAction implements ActionInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    public function __invoke(string $machineName, string $version): ProjectReleaseNotesResult
    {
        $project = $this->client->getProject($machineName);
        if ($project === null) {
            throw new \RuntimeException("Project $machineName not found.");
        }

        $rawData = $this->client->requestRaw(new Request('node.json', [
            'field_release_project' => $project->nid,
            'field_release_version' => $version,
        ]));
        $releaseList = (array) ($rawData->list ?? []);

        if ($releaseList === [] && preg_match('/^[0-9]+\.[0-9]+\.0$/', $version)) {
            $versionParts = explode('.', $version);
            $version = '8.x-' . $versionParts[0] . '.' . $versionParts[1];
            $rawData = $this->client->requestRaw(new Request('node.json', [
                'field_release_project' => $project->nid,
                'field_release_version' => $version,
            ]));
            $releaseList = (array) ($rawData->list ?? []);
        }

        if ($releaseList === []) {
            throw new \RuntimeException("No release found for $version");
        }

        return new ProjectReleaseNotesResult(
            projectName: $project->machineName,
            version: $version,
            body: (string) ($releaseList[0]->body->value ?? ''),
        );
    }
}
