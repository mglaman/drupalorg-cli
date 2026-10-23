<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\GitLab\Entity;

class GitLabNote implements \JsonSerializable
{
    /**
     * The Drupal.org bot that answers slash commands on work items.
     */
    public const BOT_USERNAME = 'drupalbot';

    /**
     * @param string|null $path
     *   The file an inline merge request note (DiffNote) is attached to.
     * @param int|null $line
     *   The line an inline note is attached to: the new line, or the old line
     *   when the note is on a removed line.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $body,
        public readonly string $author,
        public readonly string $createdAt,
        public readonly bool $system,
        public readonly ?string $path = null,
        public readonly ?int $line = null,
    ) {
    }

    public static function fromStdClass(\stdClass $data): self
    {
        $position = isset($data->position) && $data->position instanceof \stdClass ? $data->position : null;
        $path = $position->new_path ?? $position->old_path ?? null;
        $line = $position->new_line ?? $position->old_line ?? null;

        return new self(
            id: (int) ($data->id ?? 0),
            body: (string) ($data->body ?? ''),
            author: (string) ($data->author->username ?? $data->author->name ?? ''),
            createdAt: (string) ($data->created_at ?? ''),
            system: (bool) ($data->system ?? false),
            path: $path !== null ? (string) $path : null,
            line: $line !== null ? (int) $line : null,
        );
    }

    public function isFromBot(): bool
    {
        return $this->author === self::BOT_USERNAME;
    }

    public function jsonSerialize(): mixed
    {
        $data = [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->author,
            'created_at' => $this->createdAt,
        ];
        if ($this->path !== null) {
            $data['path'] = $this->path;
            $data['line'] = $this->line;
        }
        return $data;
    }
}
