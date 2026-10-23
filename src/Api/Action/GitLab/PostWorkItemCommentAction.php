<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\WorkItemRefResolver;
use mglaman\DrupalOrg\Result\Issue\WorkItemCommentResult;

/**
 * Posts a plain comment (note) on a GitLab work item.
 */
class PostWorkItemCommentAction
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    public function __invoke(string $refOrNid, string $body): WorkItemCommentResult
    {
        if (trim($body) === '') {
            throw new \InvalidArgumentException('The comment body is empty.');
        }

        $ref = (new WorkItemRefResolver($this->client))->resolve($refOrNid);

        try {
            $response = $this->gitLabClient->postIssueNote(
                $ref->projectPath,
                $ref->issueId,
                $body,
            );
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                'Failed to post a comment to %s#%d: %s',
                $ref->projectPath,
                $ref->issueId,
                $e->getMessage(),
            ), 0, $e);
        }

        if (!isset($response->id) || !is_numeric($response->id)) {
            throw new \RuntimeException(sprintf(
                'GitLab note response for %s#%d did not contain an id. The '
                . 'comment may not have been posted.',
                $ref->projectPath,
                $ref->issueId,
            ));
        }

        return new WorkItemCommentResult(
            projectPath: $ref->projectPath,
            issueIid: $ref->issueId,
            noteId: (int) $response->id,
        );
    }
}
