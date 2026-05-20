<?php

namespace mglaman\DrupalOrg\GitLab;

use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;
use Symfony\Component\Process\Process;

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

        $token = self::resolveToken();
        if ($token !== null) {
            $headers['PRIVATE-TOKEN'] = $token;
        }

        $this->client = new \GuzzleHttp\Client([
            'base_uri' => self::API_URL,
            'handler' => $stack,
            'headers' => $headers,
        ]);
    }

    /**
     * Resolve a GitLab token from env or, as a fallback, the glab CLI.
     */
    private static function resolveToken(): ?string
    {
        $token = getenv('DRUPALORG_GITLAB_TOKEN');
        if ($token !== false && $token !== '') {
            return $token;
        }
        try {
            $process = new Process(['glab', 'config', 'get', 'token', '--host', 'git.drupalcode.org']);
            $process->run();
            if ($process->isSuccessful()) {
                $output = trim($process->getOutput());
                if ($output !== '') {
                    return $output;
                }
            }
        } catch (\Throwable) {
            // glab not installed or failed; treat as unauthenticated.
        }
        return null;
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
     * @param array<string, mixed> $body
     * @throws \Exception
     */
    private function post(string $path, array $body): mixed
    {
        $res = $this->client->request('POST', $path, ['json' => $body]);
        $status = $res->getStatusCode();
        if ($status === 200 || $status === 201) {
            return \json_decode($res->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
        }
        throw new \Exception('GitLab API error', $status);
    }

    /**
     * POST /projects/{path}/issues/{iid}/notes
     *
     * Posts a note (comment) to a GitLab issue or work item. Used to send
     * Drupal.org bot slash commands such as `/do:fork`, `/do:assign me`, and
     * `/do:label ~state::needsReview` on projects whose issue queue lives on
     * GitLab work items.
     *
     * @throws \Exception
     */
    public function postIssueNote(string $projectPath, int $iid, string $body): \stdClass
    {
        /** @var \stdClass $result */
        $result = $this->post(
            'projects/' . urlencode($projectPath) . '/issues/' . $iid . '/notes',
            ['body' => $body],
        );
        return $result;
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
     * GET /projects/{path}/issues/{iid}
     *
     * @throws \Exception
     */
    public function getIssue(string $projectPath, int $iid): \stdClass
    {
        /** @var \stdClass $result */
        $result = $this->get('projects/' . urlencode($projectPath) . '/issues/' . $iid);
        return $result;
    }

    /**
     * GET /projects/{path}/issues
     *
     * @param array<string, mixed> $params
     * @return \stdClass[]
     * @throws \Exception
     */
    public function getIssues(string $projectPath, array $params = []): array
    {
        $result = $this->get('projects/' . urlencode($projectPath) . '/issues', $params);
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
