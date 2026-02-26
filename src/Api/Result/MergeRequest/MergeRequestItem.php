<?php

namespace mglaman\DrupalOrg\Result\MergeRequest;

class MergeRequestItem
{
    public function __construct(
        public readonly int $iid,
        public readonly string $title,
        public readonly string $sourceBranch,
        public readonly string $targetBranch,
        public readonly string $state,
        public readonly string $webUrl,
        public readonly bool $isMergeable,
        public readonly string $author,
        public readonly string $updatedAt,
    ) {
    }

    public static function fromStdClass(\stdClass $mr): self
    {
        return new self(
            iid: (int) $mr->iid,
            title: (string) $mr->title,
            sourceBranch: (string) $mr->source_branch,
            targetBranch: (string) $mr->target_branch,
            state: (string) $mr->state,
            webUrl: (string) $mr->web_url,
            isMergeable: ($mr->merge_status ?? '') === 'can_be_merged',
            author: (string) ($mr->author->username ?? ''),
            updatedAt: (string) ($mr->updated_at ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'iid' => $this->iid,
            'title' => $this->title,
            'source_branch' => $this->sourceBranch,
            'target_branch' => $this->targetBranch,
            'state' => $this->state,
            'web_url' => $this->webUrl,
            'is_mergeable' => $this->isMergeable,
            'author' => $this->author,
            'updated_at' => $this->updatedAt,
        ];
    }
}
