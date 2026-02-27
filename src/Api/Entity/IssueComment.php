<?php

namespace mglaman\DrupalOrg\Entity;

class IssueComment implements \JsonSerializable
{
    public function __construct(
        public readonly string $cid,
        public readonly ?string $bodyValue,
        public readonly int $created,
        public readonly ?string $authorId,
        public readonly string $authorName,
    ) {
    }

    public static function fromStdClass(\stdClass $data): self
    {
        return new self(
            cid: (string) ($data->cid ?? ''),
            bodyValue: isset($data->comment_body->value) ? (string) $data->comment_body->value : null,
            created: (int) ($data->created ?? 0),
            authorId: isset($data->author->id) ? (string) $data->author->id : null,
            authorName: (string) ($data->name ?? ''),
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'cid' => $this->cid,
            'body_value' => $this->bodyValue,
            'created' => $this->created,
            'author_id' => $this->authorId,
            'author_name' => $this->authorName,
        ];
    }
}
