<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\GitLab\MergeRequestRef;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestLogsResult;

class GetMergeRequestLogsAction extends AbstractMergeRequestAction
{
    private const TRACE_EXCERPT_LINES = 100;

    public function __invoke(string $nid, int $mrIid, ?MergeRequestRef $ref = null): MergeRequestLogsResult
    {
        [$projectId] = $ref !== null ? $this->resolveFromRef($ref) : $this->resolveGitLabProject($nid);

        $pipelines = $this->gitLabClient->getMergeRequestPipelines($projectId, $mrIid);

        if ($pipelines === []) {
            return new MergeRequestLogsResult(
                iid: $mrIid,
                pipelineId: null,
                failedJobs: [],
            );
        }

        $latest = $pipelines[0];
        $pipelineId = (int) $latest->id;

        $jobs = $this->gitLabClient->getPipelineJobs($projectId, $pipelineId);
        $failedJobs = [];

        foreach ($jobs as $job) {
            if (($job->status ?? '') !== 'failed') {
                continue;
            }
            $jobId = (int) $job->id;
            $jobName = (string) ($job->name ?? 'unknown');

            try {
                $trace = $this->gitLabClient->getJobTrace($projectId, $jobId);
                $lines = explode("\n", $trace);
                $excerpt = implode("\n", array_slice($lines, -self::TRACE_EXCERPT_LINES));
            } catch (\Exception $e) {
                $excerpt = '(trace unavailable)';
            }

            $failedJobs[] = [
                'name' => $jobName,
                'trace_excerpt' => $excerpt,
            ];
        }

        return new MergeRequestLogsResult(
            iid: $mrIid,
            pipelineId: $pipelineId,
            failedJobs: $failedJobs,
        );
    }
}
