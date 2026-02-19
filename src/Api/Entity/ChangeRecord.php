<?php

namespace mglaman\DrupalOrg\Entity;

class ChangeRecord
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
    ) {
    }

    public static function fromStdClass(\stdClass $data): self
    {
        return new self(
            title: (string) ($data->title ?? ''),
            url: (string) ($data->url ?? ''),
        );
    }
}
