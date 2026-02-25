<?php

namespace mglaman\DrupalOrg\Result\Project;

use mglaman\DrupalOrg\Entity\Release;
use mglaman\DrupalOrg\Result\ResultInterface;

class ProjectReleasesResult implements ResultInterface
{
    /**
     * @param Release[] $releases
     */
    public function __construct(
        public readonly string $projectTitle,
        public readonly string $projectName,
        public readonly array $releases,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project_title' => $this->projectTitle,
            'project_name' => $this->projectName,
            'releases' => array_map(
                static fn(Release $release) => [
                    'nid' => $release->nid,
                    'field_release_version' => $release->fieldReleaseVersion,
                    'field_release_version_extra' => $release->fieldReleaseVersionExtra,
                    'field_release_short_description' => $release->fieldReleaseShortDescription,
                    'created' => $release->created,
                ],
                $this->releases
            ),
        ];
    }
}
