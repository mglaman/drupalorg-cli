<?php

namespace mglaman\DrupalOrg\Entity;

class PiftJob
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $fileId,
        public readonly string $issueNid,
        public readonly string $status,
        public readonly string $result,
        public readonly string $message,
        public readonly int $updated,
        public readonly string $ciUrl,
    ) {
    }

    public static function fromStdClass(\stdClass $data): self
    {
        return new self(
            jobId: (string) ($data->job_id ?? ''),
            fileId: (string) ($data->file_id ?? ''),
            issueNid: (string) ($data->issue_nid ?? ''),
            status: (string) ($data->status ?? ''),
            result: (string) ($data->result ?? ''),
            message: (string) ($data->message ?? ''),
            updated: (int) ($data->updated ?? 0),
            ciUrl: (string) ($data->ci_url ?? ''),
        );
    }
}
