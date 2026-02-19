<?php

namespace mglaman\DrupalOrg\Entity;

class File
{
    public function __construct(
        public readonly string $fid,
        public readonly string $name,
        public readonly string $url,
    ) {
    }

    public static function fromStdClass(\stdClass $data): self
    {
        return new self(
            fid: (string) ($data->fid ?? ''),
            name: (string) ($data->name ?? ''),
            url: (string) ($data->url ?? ''),
        );
    }
}
