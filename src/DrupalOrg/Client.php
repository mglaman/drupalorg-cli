<?php

namespace mglaman\DrupalOrgCli\DrupalOrg;

class Client {
    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;
    /**
     * @var string
     */
    protected $apiUrl = 'https://www.drupal.org/api-d7/';

    public function __construct()
    {
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $this->apiUrl,
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'DrupalOrgCli/0.0.1',
                'Accept' => 'application/json',
                'Accept-Encoding' => '*',
            ]
        ]);
    }

    /**
     * @param $endpoint
     * @param array $options
     * @return RawResponse
     * @throws \Exception
     */
    public function request($endpoint, array $options = []) {
        $res = $this->client->request('GET', $endpoint, $options);
        if ($res->getStatusCode() == 200) {
            return new RawResponse((string) $res->getBody());
        }

        throw new \Exception('Error code', $res->getStatusCode());
    }

    /**
     * @param $nid
     * @return \mglaman\DrupalOrgCli\DrupalOrg\RawResponse
     */
    public function getNode($nid) {
        return $this->request('node/' . $nid);
    }

    public function getFile($fid) {
        return $this->request('file/' . $fid);
    }

    public function getPiftJob($jobId) {
        return $this->request('pift_ci_job/' . $jobId);
    }

    public function getPiftJobs(array $options) {
        $options += [
            'sort' => 'job_id',
            'direction' => 'DESC',
        ];
        // Guzzle hates me and parameters.
        return $this->request('https://www.drupal.org/api-d7/pift_ci_job.json?issue_nid=' . $options['issue_nid'] . '&sort='. $options['sort'] . '&direction=' . $options['direction']);
    }

    /**
     * @param $machineName
     * @return \mglaman\DrupalOrgCli\DrupalOrg\RawResponse
     */
    public function getProject($machineName) {
        return $this->request('https://www.drupal.org/api-d7/node.json?field_project_machine_name=' . $machineName);
    }

    public function getProjectReleases($projectNid, array $options = []) {
        $options += [
          'field_release_project' => $projectNid,
          'type' => 'project_release',
            // No Dec by default.
            'field_release_build_type' => 'static',
            // Current releases only, by default.
//          'field_release_update_status' => 0,
          'sort' => 'nid',
          'direction' => 'DESC',
            'limit' => 5,
        ];

        return $this->request('node.json?' . http_build_query($options));
    }
}