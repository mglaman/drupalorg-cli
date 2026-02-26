<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestFilesResult;

class GetMergeRequestFilesAction implements ActionInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    public function __invoke(string $nid, int $mrIid): MergeRequestFilesResult
    {
        $issue = $this->client->getNode($nid);
        $projectMachineName = $issue->fieldProjectMachineName;
        $remoteName = $projectMachineName . '-' . $nid;
        $gitLabProjectPath = 'issue/' . $remoteName;

        $encodedPath = urlencode($gitLabProjectPath);
        $project = $this->gitLabClient->getProject($encodedPath);
        $projectId = (int) $project->id;

        $diffs = $this->gitLabClient->getMergeRequestDiffs($projectId, $mrIid);

        $files = array_map(static function (\stdClass $d): array {
            return [
                'path' => (string) $d->new_path,
                'new_file' => (bool) ($d->new_file ?? false),
                'deleted_file' => (bool) ($d->deleted_file ?? false),
                'renamed_file' => (bool) ($d->renamed_file ?? false),
            ];
        }, $diffs);

        return new MergeRequestFilesResult(
            iid: $mrIid,
            files: array_values($files),
        );
    }
}
