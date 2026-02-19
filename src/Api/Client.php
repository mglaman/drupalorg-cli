<?php

namespace mglaman\DrupalOrg;

use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use mglaman\DrupalOrg\Entity\File;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Entity\PiftJob;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\Entity\Release;
use mglaman\DrupalOrgCli\Cache;

class Client
{

    /**
     * @var \GuzzleHttp\Client
     */
    protected \GuzzleHttp\Client $client;

    /**
     * @var string
     */
    public const API_URL = 'https://www.drupal.org/api-d7/';

    public function __construct()
    {
        $stack = HandlerStack::create();
        $stack->push(
            new CacheMiddleware(
                new PublicCacheStrategy(
                    new Psr6CacheStorage(Cache::getCache())
                )
            ),
            'cache'
        );
        $stack->push(GuzzleRetryMiddleware::factory([
            'max_retry_attempts' => 5,
            'retry_on_status' => [429, 503],
            'default_retry_multiplier' => 1.5,
        ]), 'retry');

        $this->client = new \GuzzleHttp\Client(
            [
                'base_uri' => self::API_URL,
                'cookies' => true,
                'handler' => $stack,
                'headers' => [
                    'User-Agent' => 'DrupalOrgCli/0.0.1',
                    'Accept' => 'application/json',
                    'Accept-Encoding' => '*',
                ],
            ]
        );
    }

    public function getGuzzleClient(): \GuzzleHttp\Client
    {
        return $this->client;
    }

    /**
     * @throws \Exception
     */
    private function request(Request $request): \stdClass
    {
        $res = $this->client->request('GET', $request->getUrl());
        if ($res->getStatusCode() === 200) {
            return \json_decode($res->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
        }

        throw new \Exception('Error code', $res->getStatusCode());
    }

    /**
     * Perform a raw request and return the decoded JSON response.
     *
     * @throws \Exception
     */
    public function requestRaw(Request $request): \stdClass
    {
        return $this->request($request);
    }

    public function getNode(string $nid): IssueNode
    {
        return IssueNode::fromStdClass($this->request(new Request('node/' . $nid)));
    }

    public function getFile(string $fid): File
    {
        return File::fromStdClass($this->request(new Request('file/' . $fid)));
    }

    public function getPiftJob(string $jobId): PiftJob
    {
        return PiftJob::fromStdClass(
            $this->request(
                new Request(
                    'pift_ci_job/' . $jobId,
                    [
                        'time' => time(),
                    ]
                )
            )
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return PiftJob[]
     */
    public function getPiftJobs(array $options): array
    {
        $options += [
            'sort' => 'job_id',
            'direction' => 'DESC',
        ];

        $data = $this->request(new Request('pift_ci_job.json', $options));
        return array_map(
            static fn(\stdClass $job) => PiftJob::fromStdClass($job),
            (array) ($data->list ?? [])
        );
    }

    public function getProject(string $machineName): ?Project
    {
        $data = $this->request(
            new Request(
                'node.json',
                [
                    'field_project_machine_name' => $machineName,
                ]
            )
        );
        $list = (array) ($data->list ?? []);
        if ($list === []) {
            return null;
        }
        return Project::fromStdClass($list[0]);
    }

    /**
     * @param array<string, mixed> $options
     * @return Release[]
     */
    public function getProjectReleases(
        string $projectNid,
        array $options = []
    ): array {
        $options += [
            'field_release_project' => $projectNid,
            'type' => 'project_release',
            // No `dev` by default.
            'field_release_build_type' => 'static',
            'sort' => 'nid',
            'direction' => 'DESC',
            'limit' => 20,
        ];

        $data = $this->request(new Request('node.json', $options));
        return array_map(
            static fn(\stdClass $release) => Release::fromStdClass($release),
            (array) ($data->list ?? [])
        );
    }
}
