<?php

namespace mglaman\DrupalOrg;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use mglaman\DrupalOrg\Entity\ChangeRecord;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Entity\Project;

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
     * Get the project entity (nid + issue queue type) from a machine name.
     *
     * Reads field_project_has_issue_queue so callers can tell whether issues
     * live on the legacy Drupal.org queue or on GitLab work items.
     */
    public function getProject(string $machineName): ?Project
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
            return Project::fromStdClass($data->list[0]);
        } catch (RequestException) {
            return null;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * Fetch contributors for multiple issues concurrently using promises.
     *
     * @param array<string, string> $sourceLinks Map of id => contribution source URI
     *   (a Drupal.org node URL for legacy issues, or a GitLab work item URL).
     * @return array<string, list<string>> Map of id => contributor display names
     */
    public function getContributorsFromJsonApi(array $sourceLinks): array
    {
        if ($sourceLinks === []) {
            return [];
        }

        $contributors = [];

        try {
            $promises = [];
            foreach ($sourceLinks as $id => $sourceLink) {
                $url = sprintf(
                    'https://www.drupal.org/jsonapi/node/contribution_record?filter[field_source_link.uri]=%s&filter[field_contributors.field_credit_this_contributor]=1&include=field_contributors.field_contributor_user&fields[node--contribution_record]=field_contributors&fields[paragraph--contributor]=field_contributor_user,field_credit_this_contributor&fields[user--user]=display_name',
                    urlencode($sourceLink)
                );
                $promises[$id] = $this->client->requestAsync('GET', $url);
            }

            $results = Utils::settle($promises)->wait();

            foreach ($results as $id => $result) {
                if ($result['state'] === PromiseInterface::FULFILLED) {
                    try {
                        $data = \json_decode((string) $result['value']->getBody(), false, 512, JSON_THROW_ON_ERROR);
                        $contributors[$id] = $this->extractContributorsFromJsonApiResponse($data);
                    } catch (\JsonException) {
                        $contributors[$id] = [];
                    }
                } else {
                    $contributors[$id] = [];
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
     * @return list<string> Array of contributor display names
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
     * @param list<string> $nids Array of issue node IDs
     * @return array<string, IssueNode|null> Associative array mapping nid => IssueNode (or null)
     */
    public function getIssueDetails(array $nids): array
    {
        if ($nids === []) {
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
                        $data = \json_decode((string) $result['value']->getBody(), false, 512, JSON_THROW_ON_ERROR);
                        $issues[$nid] = IssueNode::fromStdClass($data);
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
     * @return ChangeRecord[] Array of change record objects
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
            return array_map(
                static fn(\stdClass $record) => ChangeRecord::fromStdClass($record),
                (array) $data->list
            );
        } catch (RequestException) {
            return [];
        } catch (\JsonException) {
            return [];
        }
    }
}
