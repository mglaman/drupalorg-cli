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
        // Merge request pipelines run on the issue fork, so jobs and traces
        // live under the pipeline's project rather than the target project.
        $pipelineProjectId = (int) ($latest->project_id ?? $projectId);

        $jobs = $this->gitLabClient->getPipelineJobs($pipelineProjectId, $pipelineId);
        $failedJobs = [];

        foreach ($jobs as $job) {
            if (($job->status ?? '') !== 'failed') {
                continue;
            }
            $jobName = (string) ($job->name ?? 'unknown');

            try {
                $trace = $this->fetchTrace($pipelineProjectId, $job);
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

    /**
     * The API trace endpoint answers 401 without a token. The web raw endpoint
     * serves the same log anonymously, so it is the fallback.
     *
     * @throws \Exception
     */
    private function fetchTrace(int $projectId, \stdClass $job): string
    {
        try {
            return $this->gitLabClient->getJobTrace($projectId, (int) $job->id);
        } catch (\Exception $apiException) {
            $webUrl = (string) ($job->web_url ?? '');
            if ($webUrl === '') {
                throw $apiException;
            }
            return $this->gitLabClient->getJobRawLog($webUrl);
        }
    }
}
