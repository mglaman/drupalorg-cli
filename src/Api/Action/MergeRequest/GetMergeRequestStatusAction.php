<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestStatusResult;

class GetMergeRequestStatusAction implements ActionInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    public function __invoke(string $nid, int $mrIid): MergeRequestStatusResult
    {
        $issue = $this->client->getNode($nid);
        $projectMachineName = $issue->fieldProjectMachineName;
        $remoteName = $projectMachineName . '-' . $nid;
        $gitLabProjectPath = 'issue/' . $remoteName;

        $encodedPath = urlencode($gitLabProjectPath);
        $project = $this->gitLabClient->getProject($encodedPath);
        $projectId = (int) $project->id;

        $pipelines = $this->gitLabClient->getMergeRequestPipelines($projectId, $mrIid);

        if ($pipelines === []) {
            return new MergeRequestStatusResult(
                iid: $mrIid,
                pipelineId: null,
                status: 'none',
                pipelineUrl: null,
            );
        }

        $latest = $pipelines[0];
        $statusMap = [
            'success' => 'passed',
            'failed' => 'failed',
            'running' => 'running',
            'pending' => 'pending',
            'canceled' => 'canceled',
            'created' => 'pending',
            'waiting_for_resource' => 'pending',
            'preparing' => 'pending',
            'scheduled' => 'pending',
            'skipped' => 'canceled',
            'manual' => 'pending',
        ];
        $rawStatus = (string) ($latest->status ?? 'none');
        $status = $statusMap[$rawStatus] ?? $rawStatus;

        return new MergeRequestStatusResult(
            iid: $mrIid,
            pipelineId: (int) $latest->id,
            status: $status,
            pipelineUrl: (string) ($latest->web_url ?? ''),
        );
    }
}
