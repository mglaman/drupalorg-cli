<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\GitLab;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\MigratedIssueException;

/**
 * Resolves a work item reference from user input.
 *
 * Accepts a full work item URL, `project/name#nid`, `name#nid`, or a bare
 * NID. A bare NID is looked up on Drupal.org to find its project, or the
 * work item it moved to.
 */
class WorkItemRefResolver
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function resolve(string $refOrNid): WorkItemRef
    {
        $refOrNid = trim($refOrNid);
        $ref = WorkItemRef::tryParse($refOrNid);
        if ($ref !== null) {
            return $ref;
        }
        if ($refOrNid === '' || !ctype_digit($refOrNid)) {
            throw new \InvalidArgumentException(sprintf(
                'Unrecognised work item reference "%s". Expected a NID, '
                . 'shorthand (project_name#nid), or full work item URL.',
                $refOrNid,
            ));
        }
        try {
            $node = $this->client->getNode($refOrNid);
        } catch (MigratedIssueException $e) {
            return $e->ref;
        }
        $machineName = $node->fieldProjectMachineName;
        if ($machineName === '') {
            throw new \RuntimeException(sprintf(
                'Could not resolve project for NID %s.',
                $refOrNid,
            ));
        }
        return new WorkItemRef(
            projectPath: 'project/' . $machineName,
            issueId: (int) $refOrNid,
        );
    }
}
