<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\GitLab\Entity;

class GitLabIssue
{
    /**
     * @param string[] $labels
     * @param string[] $assignees
     */
    public function __construct(
        public int $iid,
        public string $title,
        public string $description,
        public string $state,
        public array $labels,
        public string $createdAt,
        public string $updatedAt,
        public string $webUrl,
        public string $author,
        public array $assignees,
    ) {
    }

    public static function fromStdClass(\stdClass $data): self
    {
        $assignees = array_map(
            static fn(\stdClass $a) => (string) ($a->username ?? $a->name ?? ''),
            (array) ($data->assignees ?? [])
        );

        return new self(
            iid: (int) $data->iid,
            title: (string) ($data->title ?? ''),
            description: (string) ($data->description ?? ''),
            state: (string) ($data->state ?? ''),
            labels: (array) ($data->labels ?? []),
            createdAt: (string) ($data->created_at ?? ''),
            updatedAt: (string) ($data->updated_at ?? ''),
            webUrl: (string) ($data->web_url ?? ''),
            author: (string) ($data->author->username ?? $data->author->name ?? ''),
            assignees: $assignees,
        );
    }
}
