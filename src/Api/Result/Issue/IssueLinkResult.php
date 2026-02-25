<?php

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Result\ResultInterface;

class IssueLinkResult implements ResultInterface
{
    public function __construct(
        public readonly string $url,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'url' => $this->url,
        ];
    }
}
