<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\GitLab\Entity;

class GitLabNote implements \JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $body,
        public readonly string $author,
        public readonly string $createdAt,
        public readonly bool $system,
    ) {
    }

    public static function fromStdClass(\stdClass $data): self
    {
        return new self(
            id: (int) ($data->id ?? 0),
            body: (string) ($data->body ?? ''),
            author: (string) ($data->author->username ?? $data->author->name ?? ''),
            createdAt: (string) ($data->created_at ?? ''),
            system: (bool) ($data->system ?? false),
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->author,
            'created_at' => $this->createdAt,
        ];
    }
}
