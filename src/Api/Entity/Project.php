<?php

namespace mglaman\DrupalOrg\Entity;

class Project
{
    public function __construct(
        public readonly string $nid,
        public readonly string $title,
        public readonly string $machineName,
        public readonly bool $hasIssueQueue = true,
    ) {
    }

    public static function fromStdClass(\stdClass $data): self
    {
        return new self(
            nid: (string) ($data->nid ?? ''),
            title: (string) ($data->title ?? ''),
            machineName: (string) ($data->field_project_machine_name ?? ''),
            hasIssueQueue: (bool) ($data->field_project_has_issue_queue ?? true),
        );
    }
}
