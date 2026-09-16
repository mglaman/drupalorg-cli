<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg;

/**
 * Resolves the project machine name that owns an issue id.
 *
 * GitLab work-item ids on migrated projects share the number space with
 * Drupal.org node ids, so a bare id can resolve to an unrelated node. The
 * caller's own knowledge therefore wins over the Drupal.org lookup:
 *
 *   1. An explicit project qualifier (project#id, work-item URL).
 *   2. The project of the git repository the command runs in, checked
 *      against Drupal.org when the node exists.
 *   3. The Drupal.org node lookup. A node that moved to a GitLab work item
 *      names its project in the redirect, so that counts as a lookup too.
 */
final class IssueProjectResolver
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @throws \RuntimeException
     *   When the project cannot be resolved, or when Drupal.org and the
     *   repository disagree about which project owns the id.
     */
    public function resolve(string $nid, ?string $explicitProject = null, ?string $repositoryProject = null): string
    {
        if ($explicitProject !== null && $explicitProject !== '') {
            return $explicitProject;
        }

        if ($repositoryProject !== null && $repositoryProject !== '') {
            return $this->resolveAgainstRepository($nid, $repositoryProject);
        }

        try {
            $nodeProject = $this->nodeProject($nid);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(
                sprintf('%s %s', $e->getMessage(), self::qualifierHint($nid)),
                0,
                $e
            );
        }
        if ($nodeProject === '') {
            throw new \RuntimeException(sprintf(
                'Could not resolve a project for issue %s. %s',
                $nid,
                self::qualifierHint($nid)
            ));
        }
        return $nodeProject;
    }

    private function resolveAgainstRepository(string $nid, string $repositoryProject): string
    {
        try {
            $nodeProject = $this->client->getNode($nid)->fieldProjectMachineName;
        } catch (MigratedIssueException $e) {
            $workItemProject = $e->ref->projectMachineName();
            if ($workItemProject === $repositoryProject) {
                return $repositoryProject;
            }
            throw new \RuntimeException(sprintf(
                'Issue %1$s is a GitLab work item in project "%2$s", but this repository is project "%3$s". '
                . 'Run this in a clone of %2$s.',
                $nid,
                $workItemProject,
                $repositoryProject
            ), 0, $e);
        } catch (\RuntimeException) {
            // Not a Drupal.org issue node, so the repository is the only
            // source for the project.
            return $repositoryProject;
        }

        if ($nodeProject === '' || $nodeProject === $repositoryProject) {
            return $repositoryProject;
        }

        throw new \RuntimeException(sprintf(
            'Issue %1$s belongs to project "%2$s" on Drupal.org, but this repository is project "%3$s". '
            . 'Pass %3$s#%1$s for the GitLab work item or %2$s#%1$s for the Drupal.org issue.',
            $nid,
            $nodeProject,
            $repositoryProject
        ));
    }

    /**
     * @throws \RuntimeException
     */
    private function nodeProject(string $nid): string
    {
        try {
            return $this->client->getNode($nid)->fieldProjectMachineName;
        } catch (MigratedIssueException $e) {
            return $e->ref->projectMachineName();
        }
    }

    private static function qualifierHint(string $nid): string
    {
        return sprintf('Pass the project explicitly as project#%s.', $nid);
    }
}
