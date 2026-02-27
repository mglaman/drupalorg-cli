<?php

namespace mglaman\DrupalOrg\Result\MergeRequest;

use mglaman\DrupalOrg\Result\ResultInterface;

class MergeRequestLogsResult implements ResultInterface
{
    /**
     * @param array<int, array<string, string>> $failedJobs
     */
    public function __construct(
        public readonly int $iid,
        public readonly ?int $pipelineId,
        public readonly array $failedJobs,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'iid' => $this->iid,
            'pipeline_id' => $this->pipelineId,
            'failed_jobs' => $this->failedJobs,
        ];
    }
}
