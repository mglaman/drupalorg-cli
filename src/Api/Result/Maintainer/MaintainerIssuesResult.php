<?php

namespace mglaman\DrupalOrg\Result\Maintainer;

use mglaman\DrupalOrg\Result\ResultInterface;

class MaintainerIssuesResult implements ResultInterface
{
    /**
     * @param list<array{project: string, title: string, link: string}> $items
     */
    public function __construct(
        public readonly string $feedTitle,
        public readonly array $items,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'feed_title' => $this->feedTitle,
            'items' => $this->items,
        ];
    }
}
