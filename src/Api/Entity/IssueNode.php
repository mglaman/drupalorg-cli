<?php

namespace mglaman\DrupalOrg\Entity;

use mglaman\DrupalOrg\IssueBranchNaming;

class IssueNode
{
    /**
     * @param IssueFile[] $fieldIssueFiles
     * @param \stdClass[] $comments
     */
    public function __construct(
        public readonly string $nid,
        public readonly string $title,
        public readonly int $created,
        public readonly int $changed,
        public readonly int $commentCount,
        public readonly string $fieldIssueVersion,
        public readonly int $fieldIssueStatus,
        public readonly int $fieldIssueCategory,
        public readonly int $fieldIssuePriority,
        public readonly string $fieldIssueComponent,
        public readonly string $fieldProjectId,
        public readonly string $fieldProjectMachineName,
        public readonly ?string $bodyValue,
        public readonly ?string $authorId,
        public readonly array $fieldIssueFiles,
        public readonly array $comments,
    ) {
    }

    public function buildCleanTitle(): string
    {
        return IssueBranchNaming::slug($this->title);
    }

    public function buildBranchName(): string
    {
        return sprintf('%s-%s', $this->nid, $this->buildCleanTitle());
    }

    public function buildIssueVersionBranch(): string
    {
        if ($this->fieldProjectId === '3060') {
            return substr($this->fieldIssueVersion, 0, 5);
        }
        return IssueBranchNaming::versionBranch($this->fieldIssueVersion);
    }

    public static function fromStdClass(\stdClass $data): self
    {
        $fieldIssueFiles = array_map(
            static fn(\stdClass $file) => IssueFile::fromStdClass($file),
            (array) ($data->field_issue_files ?? [])
        );

        return new self(
            nid: (string) ($data->nid ?? ''),
            title: (string) ($data->title ?? ''),
            created: (int) ($data->created ?? 0),
            changed: (int) ($data->changed ?? 0),
            commentCount: (int) ($data->comment_count ?? 0),
            fieldIssueVersion: (string) ($data->field_issue_version ?? ''),
            fieldIssueStatus: (int) ($data->field_issue_status ?? 0),
            fieldIssueCategory: (int) ($data->field_issue_category ?? 0),
            fieldIssuePriority: (int) ($data->field_issue_priority ?? 0),
            fieldIssueComponent: (string) ($data->field_issue_component ?? ''),
            fieldProjectId: (string) ($data->field_project?->id ?? ''),
            fieldProjectMachineName: (string) ($data->field_project?->machine_name ?? ''),
            bodyValue: isset($data->body->value) ? (string) $data->body->value : null,
            authorId: isset($data->author->id) ? (string) $data->author->id : null,
            fieldIssueFiles: $fieldIssueFiles,
            comments: (array) ($data->comments ?? []),
        );
    }
}
