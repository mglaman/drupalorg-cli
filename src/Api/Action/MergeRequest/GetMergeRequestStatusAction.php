<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestStatusResult;

class GetMergeRequestStatusAction extends AbstractMergeRequestAction
{
    public function __invoke(string $nid, int $mrIid): MergeRequestStatusResult
    {
        [$projectId] = $this->resolveGitLabProject($nid);

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
