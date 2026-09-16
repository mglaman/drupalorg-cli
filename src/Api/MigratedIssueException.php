<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg;

use mglaman\DrupalOrg\GitLab\WorkItemRef;

/**
 * The Drupal.org node moved to a GitLab work item.
 *
 * Drupal.org answers such node IDs with a stub whose only field is new_url.
 * Callers that support work items catch this and continue with the ref.
 */
final class MigratedIssueException extends \RuntimeException
{
    public function __construct(
        public readonly string $nid,
        public readonly WorkItemRef $ref,
        string $newUrl,
    ) {
        parent::__construct(sprintf(
            'Issue %s moved to a GitLab work item at %s. Pass %s#%s.',
            $nid,
            $newUrl,
            $ref->projectMachineName(),
            $nid
        ));
    }
}
