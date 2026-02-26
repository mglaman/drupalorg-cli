<?php

namespace mglaman\DrupalOrg\Result\MergeRequest;

use mglaman\DrupalOrg\Result\ResultInterface;

class MergeRequestStatusResult implements ResultInterface
{
    public function __construct(
        public readonly int $iid,
        public readonly ?int $pipelineId,
        public readonly string $status,
        public readonly ?string $pipelineUrl,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'iid' => $this->iid,
            'pipeline_id' => $this->pipelineId,
            'status' => $this->status,
            'pipeline_url' => $this->pipelineUrl,
        ];
    }
}
