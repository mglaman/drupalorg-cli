<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\GitLab;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\ProjectRemote;

/**
 * Scopes a bare issue ID to the checkout's project when it uses work items.
 *
 * Work item IDs share the number space with Drupal.org node IDs, so a bare
 * number looked up on Drupal.org can return another project's issue. Inside a
 * clone of a project that moved to GitLab work items, a bare number almost
 * always means that project's own work item. Migrated issues keep their node
 * ID as the work item ID, so this holds for them too.
 */
final class BareIssueIdQualifier
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    /**
     * Returns `project_name#id` for a bare ID in a work item project's clone.
     *
     * Any other input, including a bare ID outside such a clone, is returned
     * unchanged.
     */
    public function qualify(string $refOrNid, ?ProjectRemote $checkout): string
    {
        $id = trim($refOrNid);
        if ($checkout === null || $id === '' || !ctype_digit($id)) {
            return $refOrNid;
        }
        $project = $this->client->getProject($checkout->machineName);
        if ($project === null || $project->hasIssueQueue) {
            return $refOrNid;
        }
        return $checkout->machineName . '#' . $id;
    }
}
