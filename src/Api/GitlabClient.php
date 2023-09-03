<?php

namespace mglaman\DrupalOrg;

use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use mglaman\DrupalOrg\GitlabRequest as Request;
use mglaman\DrupalOrgCli\Cache;

class GitlabClient
{

    /**
     * @var \GuzzleHttp\Client
     */
    protected \GuzzleHttp\Client $client;

    /**
     * @var string
     */
    public const API_URL = 'https://git.drupalcode.org/api/v4/';

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

    public function getProject(string $machineName): ?\stdClass
    {
        $request = new Request(
            'projects',
            [
                'search' => $machineName,
            ]
        );
        $res = $this->request($request);
        foreach ($res->getAll() as $project) {
            if ($project->name === $machineName) {
                return $project;
            }
        }
        return null;
    }

    public function getProjectBranches(int $projectId): array
    {
        $request = new Request(
            'projects/' . $projectId . '/repository/branches'
        );
        $res = $this->request($request);
        return array_column($res->getAll()->getArrayCopy(), 'name');
    }
}
