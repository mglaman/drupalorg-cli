<?php

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Entity\IssueComment;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Result\ResultInterface;

class IssueResult implements ResultInterface
{
    /**
     * @param IssueComment[] $comments
     */
    public function __construct(
        public readonly string $nid,
        public readonly string $title,
        public readonly int $created,
        public readonly int $changed,
        public readonly int $fieldIssueStatus,
        public readonly int $fieldIssueCategory,
        public readonly int $fieldIssuePriority,
        public readonly string $fieldIssueVersion,
        public readonly string $fieldIssueComponent,
        public readonly string $fieldProjectMachineName,
        public readonly ?string $authorId,
        public readonly ?string $bodyValue,
        public readonly array $comments = [],
    ) {
    }

    /**
     * @param IssueComment[] $comments
     */
    public static function fromIssueNode(IssueNode $issue, array $comments = []): self
    {
        return new self(
            nid: $issue->nid,
            title: $issue->title,
            created: $issue->created,
            changed: $issue->changed,
            fieldIssueStatus: $issue->fieldIssueStatus,
            fieldIssueCategory: $issue->fieldIssueCategory,
            fieldIssuePriority: $issue->fieldIssuePriority,
            fieldIssueVersion: $issue->fieldIssueVersion,
            fieldIssueComponent: $issue->fieldIssueComponent,
            fieldProjectMachineName: $issue->fieldProjectMachineName,
            authorId: $issue->authorId,
            bodyValue: $issue->bodyValue,
            comments: $comments,
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'nid' => $this->nid,
            'title' => $this->title,
            'created' => $this->created,
            'changed' => $this->changed,
            'field_issue_status' => $this->fieldIssueStatus,
            'field_issue_category' => $this->fieldIssueCategory,
            'field_issue_priority' => $this->fieldIssuePriority,
            'field_issue_version' => $this->fieldIssueVersion,
            'field_issue_component' => $this->fieldIssueComponent,
            'field_project_machine_name' => $this->fieldProjectMachineName,
            'author_id' => $this->authorId,
            'body_value' => $this->bodyValue,
            'comments' => array_map(static fn(IssueComment $c) => $c->jsonSerialize(), $this->comments),
        ];
    }
}
