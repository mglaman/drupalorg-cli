<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\Client;

/**
 * Resolves a project argument to the GitLab path of its issue queue.
 *
 * @property-read Client $client
 */
trait ResolvesGitLabProjectTrait
{
    /**
     * Accepts `name` or `project/name` and returns `project/name`.
     *
     * Projects still on the Drupal.org issue queue are refused: their issues
     * are not GitLab work items, so the GitLab issue APIs do not apply.
     */
    private function resolveGitLabProjectPath(string $project): string
    {
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
        return 'project/' . $machineName;
    }

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
