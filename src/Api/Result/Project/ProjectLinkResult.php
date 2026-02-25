<?php

namespace mglaman\DrupalOrg\Result\Project;

use mglaman\DrupalOrg\Result\ResultInterface;

class ProjectLinkResult implements ResultInterface
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
