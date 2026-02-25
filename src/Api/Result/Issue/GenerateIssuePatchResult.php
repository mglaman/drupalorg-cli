<?php

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Result\ResultInterface;

class GenerateIssuePatchResult implements ResultInterface
{
    /**
     * @param string $patchName  Filename of the generated patch, e.g. "schedule_transition-3383637-19.patch"
     * @param string $patchPath  Absolute path to the written patch file
     * @param string $diffStat   Output of `git diff --stat` for display
     */
    public function __construct(
        public readonly string $patchName,
        public readonly string $patchPath,
        public readonly string $diffStat,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'patch_name' => $this->patchName,
            'patch_path' => $this->patchPath,
            'diff_stat' => $this->diffStat,
        ];
    }
}
