<?php

namespace mglaman\DrupalOrg\GitLab;

use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;

class Client
{
    public const API_URL = 'https://git.drupalcode.org/api/v4/';

    private \GuzzleHttp\Client $client;

    public function __construct()
    {
        $stack = HandlerStack::create();
        $stack->push(GuzzleRetryMiddleware::factory([
            'max_retry_attempts' => 5,
            'retry_on_status' => [429, 503],
            'default_retry_multiplier' => 1.5,
        ]), 'retry');

        $headers = [
            'User-Agent' => 'DrupalOrgCli/0.0.1',
            'Accept' => 'application/json',
        ];

        $token = getenv('DRUPALORG_GITLAB_TOKEN');
        if ($token !== false && $token !== '') {
            $headers['PRIVATE-TOKEN'] = $token;
        }

        $this->client = new \GuzzleHttp\Client([
            'base_uri' => self::API_URL,
            'handler' => $stack,
            'headers' => $headers,
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @throws \Exception
     */
    private function get(string $path, array $query = []): mixed
    {
        $options = [];
        if ($query !== []) {
            $options['query'] = $query;
        }
        $res = $this->client->request('GET', $path, $options);
        if ($res->getStatusCode() === 200) {
            return \json_decode($res->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
        }
        throw new \Exception('GitLab API error', $res->getStatusCode());
    }

    /**
     * GET /projects/{path}
     *
     * @throws \Exception
     */
    public function getProject(string $path): \stdClass
    {
        /** @var \stdClass $result */
        $result = $this->get('projects/' . urlencode($path));
        return $result;
    }

    /**
     * GET /projects/{id}/repository/branches
     *
     * @return \stdClass[]
     * @throws \Exception
     */
    public function getBranches(string|int $projectId): array
    {
        $result = $this->get('projects/' . $projectId . '/repository/branches', ['per_page' => 100]);
        return is_array($result) ? $result : [];
    }

    /**
     * GET /projects/{id}/merge_requests
     *
     * @param array<string, mixed> $params
     * @return \stdClass[]
     * @throws \Exception
     */
    public function getMergeRequests(string|int $projectId, array $params = []): array
    {
        $result = $this->get('projects/' . $projectId . '/merge_requests', $params);
        return is_array($result) ? $result : [];
    }

    /**
     * GET /projects/{id}/merge_requests/{iid}
     *
     * @throws \Exception
     */
    public function getMergeRequest(string|int $projectId, int $mrIid): \stdClass
    {
        /** @var \stdClass $result */
        $result = $this->get('projects/' . $projectId . '/merge_requests/' . $mrIid);
        return $result;
    }

    /**
     * GET /projects/{id}/merge_requests/{iid}/diffs
     *
     * @return \stdClass[]
     * @throws \Exception
     */
    public function getMergeRequestDiffs(string|int $projectId, int $mrIid): array
    {
        $result = $this->get('projects/' . $projectId . '/merge_requests/' . $mrIid . '/diffs');
        return is_array($result) ? $result : [];
    }

    /**
     * GET /projects/{id}/merge_requests/{iid}/pipelines
     *
     * @return \stdClass[]
     * @throws \Exception
     */
    public function getMergeRequestPipelines(string|int $projectId, int $mrIid): array
    {
        $result = $this->get('projects/' . $projectId . '/merge_requests/' . $mrIid . '/pipelines');
        return is_array($result) ? $result : [];
    }

    /**
     * GET /projects/{id}/pipelines/{pipeline_id}/jobs
     *
     * @return \stdClass[]
     * @throws \Exception
     */
    public function getPipelineJobs(string|int $projectId, int $pipelineId): array
    {
        $result = $this->get('projects/' . $projectId . '/pipelines/' . $pipelineId . '/jobs');
        return is_array($result) ? $result : [];
    }

    /**
     * GET /projects/{id}/jobs/{job_id}/trace
     *
     * @throws \Exception
     */
    public function getJobTrace(string|int $projectId, int $jobId): string
    {
        $res = $this->client->request('GET', 'projects/' . $projectId . '/jobs/' . $jobId . '/trace');
        if ($res->getStatusCode() === 200) {
            return $res->getBody()->getContents();
        }
        throw new \Exception('GitLab API error fetching job trace', $res->getStatusCode());
    }
}
