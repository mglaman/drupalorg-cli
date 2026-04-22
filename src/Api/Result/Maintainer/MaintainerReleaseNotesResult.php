<?php

namespace mglaman\DrupalOrg\Result\Maintainer;

use mglaman\DrupalOrg\Entity\ChangeRecord;
use mglaman\DrupalOrg\Result\ResultInterface;

class MaintainerReleaseNotesResult implements ResultInterface
{
    /**
     * @param array<string, array<int|string, string>> $categorizedChanges category => [nid => cleanedTitle]
     * @param array<string, int> $contributors username => commit count
     * @param list<string> $nidList
     * @param ChangeRecord[] $changeRecords
     */
    public function __construct(
        public readonly string $ref1,
        public readonly string $ref2,
        public readonly string $project,
        public readonly array $categorizedChanges,
        public readonly array $contributors,
        public readonly array $nidList,
        public readonly array $changeRecords,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'ref1' => $this->ref1,
            'ref2' => $this->ref2,
            'project' => $this->project,
            'categorized_changes' => $this->categorizedChanges,
            'contributors' => $this->contributors,
            'nid_list' => $this->nidList,
            'change_records' => array_map(
                static fn(ChangeRecord $r) => ['title' => $r->title, 'url' => $r->url],
                $this->changeRecords
            ),
        ];
    }
}
