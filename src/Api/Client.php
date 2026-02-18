<?php

namespace mglaman\DrupalOrg;

use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
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
     * @param Request $request
     *
     * @return \mglaman\DrupalOrg\Response
     * @throws \Exception
     */
    public function request(Request $request): Response
    {
        $res = $this->client->request('GET', $request->getUrl());
        if ($res->getStatusCode() === 200) {
            return new Response($res->getBody()->getContents());
        }

        throw new \Exception('Error code', $res->getStatusCode());
    }

    public function getNode(string $nid): Response
    {
        return $this->request(new Request('node/' . $nid));
    }

    public function getFile(string $fid): Response
    {
        return $this->request(new Request('file/' . $fid));
    }

    public function getPiftJob(string $jobId): Response
    {
        return $this->request(
            new Request(
                'pift_ci_job/' . $jobId,
                [
                    'time' => time(),
                ]
            )
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getPiftJobs(array $options): Response
    {
        $options += [
            'sort' => 'job_id',
            'direction' => 'DESC',
        ];

        return $this->request(new Request('pift_ci_job.json', $options));
    }

    public function getProject(string $machineName): Response
    {
        $request = new Request(
            'node.json',
            [
                'field_project_machine_name' => $machineName,
            ]
        );
        return $this->request($request);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getProjectReleases(
        string $projectNid,
        array $options = []
    ): Response {
        $options += [
            'field_release_project' => $projectNid,
            'type' => 'project_release',
            // No `dev` by default.
            'field_release_build_type' => 'static',
            'sort' => 'nid',
            'direction' => 'DESC',
            'limit' => 20,
        ];

        return $this->request(new Request('node.json', $options));
    }
}
