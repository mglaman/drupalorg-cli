<?php

namespace mglaman\DrupalOrg;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;

final class DrupalOrg
{
    public function __construct(
        private readonly ClientInterface $client
    ) {
    }

    /**
     * Get project ID from project machine name.
     *
     * @param string $machineName The project machine name (e.g., "redis")
     * @return string|null The project ID (nid), or null if not found
     */
    public function getProjectId(string $machineName): ?string
    {
        try {
            $url = sprintf(
                'https://www.drupal.org/api-d7/node.json?field_project_machine_name=%s',
                urlencode($machineName)
            );
            $response = $this->client->request('GET', $url);
            $data = \json_decode((string) $response->getBody());
            if ($data === null || !isset($data->list) || count($data->list) === 0) {
                return null;
            }
            return $data->list[0]->nid ?? null;
        } catch (RequestException) {
            return null;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * Fetch contributors for multiple issues concurrently using promises.
     *
     * @param array $nids Array of issue node IDs
     * @return array Associative array mapping nid => array of contributor display names
     */
    public function getContributorsFromJsonApi(array $nids): array
    {
        if (empty($nids)) {
            return [];
        }

        $contributors = [];

        try {
            $promises = [];
            foreach ($nids as $nid) {
                $url = sprintf(
                    'https://www.drupal.org/jsonapi/node/contribution_record?filter[field_source_link.uri]=https://www.drupal.org/node/%s&filter[field_contributors.field_credit_this_contributor]=1&include=field_contributors.field_contributor_user&fields[node--contribution_record]=field_contributors&fields[paragraph--contributor]=field_contributor_user,field_credit_this_contributor&fields[user--user]=display_name',
                    urlencode($nid)
                );
                $promises[$nid] = $this->client->requestAsync('GET', $url);
            }

            $results = Utils::settle($promises)->wait();

            foreach ($results as $nid => $result) {
                if ($result['state'] === PromiseInterface::FULFILLED) {
                    try {
                        $data = \json_decode((string) $result['value']->getBody(), false, 512, JSON_THROW_ON_ERROR);
                        $contributors[$nid] = $this->extractContributorsFromJsonApiResponse($data);
                    } catch (\JsonException) {
                        $contributors[$nid] = [];
                    }
                } else {
                    $contributors[$nid] = [];
                }
            }
        } catch (\Throwable) {
            // If anything goes wrong with async, return what we have
        }

        return $contributors;
    }

    /**
     * Extract contributors from JSON:API response data.
     *
     * @param \stdClass $data The decoded JSON:API response
     * @return array Array of contributor display names
     */
    private function extractContributorsFromJsonApiResponse(\stdClass $data): array
    {
        if (!isset($data->data) || count($data->data) === 0) {
            return [];
        }

        $contributors = [];
        if (isset($data->included)) {
            foreach ($data->included as $item) {
                if ($item->type === 'user--user' && isset($item->attributes->display_name)) {
                    $displayName = $item->attributes->display_name;
                    if ($displayName !== 'System Message') {
                        $contributors[] = $displayName;
                    }
                }
            }
        }

        return $contributors;
    }

    /**
     * Fetch issue details for multiple issues concurrently.
     *
     * @param array $nids Array of issue node IDs
     * @return array Associative array mapping nid => issue data object (or null)
     */
    public function getIssueDetails(array $nids): array
    {
        if (empty($nids)) {
            return [];
        }

        $issues = [];
        $promises = [];

        try {
            foreach ($nids as $nid) {
                $url = sprintf('https://www.drupal.org/api-d7/node/%s.json', $nid);
                $promises[$nid] = $this->client->requestAsync('GET', $url);
            }

            $results = Utils::settle($promises)->wait();

            foreach ($results as $nid => $result) {
                if ($result['state'] === PromiseInterface::FULFILLED) {
                    try {
                        $issues[$nid] = \json_decode((string) $result['value']->getBody(), false, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        $issues[$nid] = null;
                    }
                } else {
                    $issues[$nid] = null;
                }
            }
        } catch (\Throwable) {
            // Silently continue on failure
        }

        return $issues;
    }

    /**
     * Fetch change records for a project and version.
     *
     * @param string $projectId The Drupal.org project ID
     * @param string $version The version to filter by (e.g., "8.x-1.9")
     * @return array Array of change record objects
     */
    public function getChangeRecords(string $projectId, string $version): array
    {
        try {
            $url = sprintf(
                'https://www.drupal.org/api-d7/node.json?type=changenotice&field_project=%s&field_change_to=%s',
                urlencode($projectId),
                urlencode($version)
            );
            $response = $this->client->request('GET', $url);
            $data = \json_decode((string) $response->getBody());
            if ($data === null || !isset($data->list)) {
                return [];
            }
            return $data->list;
        } catch (RequestException) {
            return [];
        } catch (\JsonException) {
            return [];
        }
    }
}
