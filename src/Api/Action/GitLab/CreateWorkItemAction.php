<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Enum\ProjectIssueCategory;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\Issue\WorkItemCreatedResult;

/**
 * Creates a GitLab work item in a project's issue queue.
 *
 * Only projects whose issue queue moved to GitLab can take new issues this
 * way. Drupal.org has no write API for the legacy issue queue, so those
 * projects get an error pointing at the web form.
 */
class CreateWorkItemAction
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    /**
     * @param string[] $labels
     */
    public function __invoke(
        string $project,
        string $title,
        string $description = '',
        ?ProjectIssueCategory $category = null,
        array $labels = [],
        bool $dryRun = false,
    ): WorkItemCreatedResult {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('The issue title is empty.');
        }

        $machineName = self::machineName($project);
        $projectData = $this->client->getProject($machineName);
        if ($projectData === null) {
            throw new \InvalidArgumentException(sprintf('Project %s not found.', $machineName));
        }
        if ($projectData->hasIssueQueue) {
            throw new \RuntimeException(sprintf(
                'Project %s still uses the Drupal.org issue queue, which has no '
                . 'write API. Create the issue at '
                . 'https://www.drupal.org/node/add/project-issue/%s instead.',
                $machineName,
                $machineName,
            ));
        }

        $labels = array_values(array_unique(array_filter(
            array_map('trim', [...($category !== null ? [$category->gitLabLabel()] : []), ...$labels]),
            static fn(string $label) => $label !== '',
        )));
        $projectPath = 'project/' . $machineName;

        if ($dryRun) {
            return new WorkItemCreatedResult(
                projectPath: $projectPath,
                title: $title,
                description: $description,
                labels: $labels,
            );
        }

        try {
            $response = $this->gitLabClient->createIssue($projectPath, $title, $description, $labels);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                'Failed to create an issue in %s: %s',
                $projectPath,
                $e->getMessage(),
            ), 0, $e);
        }

        if (!isset($response->iid) || !is_numeric($response->iid)) {
            throw new \RuntimeException(sprintf(
                'GitLab issue response for %s did not contain an iid. The '
                . 'issue may or may not have been created; check the project '
                . 'before retrying.',
                $projectPath,
            ));
        }

        return new WorkItemCreatedResult(
            projectPath: $projectPath,
            title: $title,
            description: $description,
            labels: $labels,
            issueIid: (int) $response->iid,
            webUrl: isset($response->web_url) && is_string($response->web_url) ? $response->web_url : null,
        );
    }

    /**
     * Accepts `name` or `project/name` and returns the machine name.
     */
    private static function machineName(string $project): string
    {
        $project = trim($project);
        if (str_starts_with($project, 'project/')) {
            $project = substr($project, strlen('project/'));
        }
        if ($project === '' || preg_match('/^[a-z0-9_]+$/', $project) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid project machine name "%s".',
                $project,
            ));
        }
        return $project;
    }
}
