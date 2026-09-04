<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\GitLab\Entity\GitLabNote;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrg\Result\GitLab\GitLabIssueResult;

class GetGitLabIssueAction
{
    /**
     * The Drupal.org bot that answers slash commands on work items.
     */
    public const BOT_USERNAME = 'drupalbot';

    public function __construct(private readonly GitLabClient $gitLabClient)
    {
    }

    public function __invoke(WorkItemRef $ref, bool $withComments = false, bool $includeBotComments = false): GitLabIssueResult
    {
        $data = $this->gitLabClient->getIssue($ref->projectPath, $ref->issueId);
        $issue = GitLabIssue::fromStdClass($data);
        if (!$withComments) {
            return new GitLabIssueResult($issue);
        }

        $comments = [];
        foreach ($this->gitLabClient->getIssueNotes($ref->projectPath, $ref->issueId) as $noteData) {
            $note = GitLabNote::fromStdClass($noteData);
            if ($note->system) {
                continue;
            }
            if (!$includeBotComments && $note->author === self::BOT_USERNAME) {
                continue;
            }
            $comments[] = $note;
        }
        return new GitLabIssueResult($issue, $comments);
    }
}
