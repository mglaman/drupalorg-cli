<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\WorkItemRefResolver;
use mglaman\DrupalOrg\Result\Issue\SlashCommandResult;

/**
 * Posts a Drupal.org bot slash command (e.g. `/do:fork`, `/do:assign me`,
 * `/do:label ~state::needsReview`) as a comment on a GitLab work item.
 *
 * Only meaningful for projects whose issue queue has been migrated to GitLab
 * work items at git.drupalcode.org. For classic Drupal.org issue queues the
 * bot is not present and the call will 404.
 */
class PostWorkItemSlashCommandAction
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    public function __invoke(string $refOrNid, string $command): SlashCommandResult
    {
        $ref = (new WorkItemRefResolver($this->client))->resolve($refOrNid);

        try {
            $response = $this->gitLabClient->postIssueNote(
                $ref->projectPath,
                $ref->issueId,
                $command,
            );
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                'Failed to post "%s" to %s#%d: %s. If this project still uses a '
                . 'Drupal.org issue queue, slash commands are not supported.',
                $command,
                $ref->projectPath,
                $ref->issueId,
                $e->getMessage(),
            ), 0, $e);
        }

        if (!isset($response->id) || !is_numeric($response->id)) {
            throw new \RuntimeException(sprintf(
                'GitLab note response for %s#%d did not contain an id. The '
                . 'note may not have been posted.',
                $ref->projectPath,
                $ref->issueId,
            ));
        }

        return new SlashCommandResult(
            projectPath: $ref->projectPath,
            issueIid: $ref->issueId,
            command: $command,
            noteId: (int) $response->id,
        );
    }
}
