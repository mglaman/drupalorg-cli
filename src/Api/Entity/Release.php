<?php

namespace mglaman\DrupalOrg\Entity;

class Release
{
    public function __construct(
        public readonly string $nid,
        public readonly string $fieldReleaseVersion,
        public readonly ?string $fieldReleaseVersionExtra,
        public readonly ?string $fieldReleaseShortDescription,
        public readonly int $created,
        public readonly string $fieldReleaseProject,
        public readonly ?string $bodyValue,
    ) {
    }

    public static function fromStdClass(\stdClass $data): self
    {
        return new self(
            nid: (string) ($data->nid ?? ''),
            fieldReleaseVersion: (string) ($data->field_release_version ?? ''),
            fieldReleaseVersionExtra: isset($data->field_release_version_extra) ? (string) $data->field_release_version_extra : null,
            fieldReleaseShortDescription: isset($data->field_release_short_description) ? (string) $data->field_release_short_description : null,
            created: (int) ($data->created ?? 0),
            fieldReleaseProject: (string) ($data->field_release_project ?? ''),
            bodyValue: isset($data->body->value) ? (string) $data->body->value : null,
        );
    }
}
