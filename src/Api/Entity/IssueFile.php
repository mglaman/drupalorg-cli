<?php

namespace mglaman\DrupalOrg\Entity;

class IssueFile
{
    public function __construct(
        public readonly bool $display,
        public readonly string $fileId,
        public readonly int $cid,
    ) {
    }

    public static function fromStdClass(\stdClass $data): self
    {
        return new self(
            display: (bool) ($data->display ?? false),
            fileId: (string) ($data->file->id ?? ''),
            cid: (int) ($data->file->cid ?? 0),
        );
    }
}
