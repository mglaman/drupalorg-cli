<?php

namespace mglaman\DrupalOrg;

class ListResponse
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextLink,
        public readonly ?string $prevLink,
    ) {
    }

    public static function fromStdClass(\stdClass $data, callable $itemFactory): self
    {
        $items = array_map($itemFactory, (array) ($data->list ?? []));
        return new self(
            items: $items,
            nextLink: $data->next ?? null,
            prevLink: $data->prev ?? null,
        );
    }
}
