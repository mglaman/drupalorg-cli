<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestDiffResult;

class GetMergeRequestDiffAction implements ActionInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    public function __invoke(string $nid, int $mrIid): MergeRequestDiffResult
    {
        $issue = $this->client->getNode($nid);
        $projectMachineName = $issue->fieldProjectMachineName;
        $remoteName = $projectMachineName . '-' . $nid;
        $gitLabProjectPath = 'issue/' . $remoteName;

        $encodedPath = urlencode($gitLabProjectPath);
        $project = $this->gitLabClient->getProject($encodedPath);
        $projectId = (int) $project->id;

        $mr = $this->gitLabClient->getMergeRequest($projectId, $mrIid);
        $diffs = $this->gitLabClient->getMergeRequestDiffs($projectId, $mrIid);

        $unified = '';
        foreach ($diffs as $fileDiff) {
            $unified .= $fileDiff->diff ?? '';
        }

        return new MergeRequestDiffResult(
            iid: $mrIid,
            sourceBranch: (string) $mr->source_branch,
            targetBranch: (string) $mr->target_branch,
            diff: $unified,
        );
    }
}
