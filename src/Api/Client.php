<?php

namespace mglaman\DrupalOrg;

class Client
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;
    /**
     * @var string
     */
    const API_URL = 'https://www.drupal.org/api-d7/';

    public function __construct()
    {
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => self::API_URL,
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'DrupalOrgCli/0.0.1',
                'Accept' => 'application/json',
                'Accept-Encoding' => '*',
            ]
        ]);
    }

    /**
     * @param Request $request
     * @return \mglaman\DrupalOrg\Response
     * @throws \Exception
     */
    public function request(Request $request)
    {
        $res = $this->client->request('GET', $request->getUrl());
        if ($res->getStatusCode() == 200) {
            return new Response($res->getBody()->getContents());
        }

        throw new \Exception('Error code', $res->getStatusCode());
    }

    /**
     * @param $nid
     * @return \mglaman\DrupalOrg\RawResponse
     */
    public function getNode($nid)
    {
        return $this->request(new Request('node/' . $nid));
    }

    public function getFile($fid)
    {
        return $this->request(new Request('file/' . $fid));
    }

    public function getPiftJob($jobId)
    {
        return $this->request(new Request('pift_ci_job/' . $jobId, [
            'time' => time(),
        ]));
    }

    public function getPiftJobs(array $options)
    {
        $options += [
          'sort' => 'job_id',
          'direction' => 'DESC',
        ];

        return $this->request(new Request('pift_ci_job.json', $options));
    }

    /**
     * @param $machineName
     * @return \mglaman\DrupalOrg\Response
     */
    public function getProject($machineName)
    {
        $request = new Request('node.json', [
            'field_project_machine_name' => $machineName,
        ]);
        return $this->request($request);
    }

    public function getProjectReleases($projectNid, array $options = [])
    {
        $options += [
          'field_release_project' => $projectNid,
          'type' => 'project_release',
            // No Dec by default.
          'field_release_build_type' => 'static',
          'sort' => 'nid',
          'direction' => 'DESC',
          'limit' => 20,
        ];

        return $this->request(new Request('node.json', $options));
    }
}
