<?php

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Result\ResultInterface;

class GenerateIssueInterdiffResult implements ResultInterface
{
    /**
     * @param string $interdiffName  Filename of the generated interdiff, e.g. "interdiff-3383637-5-19.txt"
     * @param string $interdiffPath  Absolute path to the written interdiff file
     * @param string $diffStat       Output of `git diff --stat` for display
     */
    public function __construct(
        public readonly string $interdiffName,
        public readonly string $interdiffPath,
        public readonly string $diffStat,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'interdiff_name' => $this->interdiffName,
            'interdiff_path' => $this->interdiffPath,
            'diff_stat' => $this->diffStat,
        ];
    }
}
