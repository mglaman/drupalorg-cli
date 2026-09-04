<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Result\GitLab;

use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\GitLab\Entity\GitLabNote;
use mglaman\DrupalOrg\Result\ResultInterface;

class GitLabIssueResult implements ResultInterface
{
    /**
     * @param GitLabNote[] $comments
     */
    public function __construct(
        public readonly GitLabIssue $issue,
        public readonly array $comments = [],
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'iid' => $this->issue->iid,
            'title' => $this->issue->title,
            'description' => $this->issue->description,
            'state' => $this->issue->state,
            'labels' => $this->issue->labels,
            'created_at' => $this->issue->createdAt,
            'updated_at' => $this->issue->updatedAt,
            'web_url' => $this->issue->webUrl,
            'author' => $this->issue->author,
            'assignees' => $this->issue->assignees,
            'comments' => array_map(static fn(GitLabNote $note) => $note->jsonSerialize(), $this->comments),
        ];
    }
}
