<?php

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Entity\File;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Result\ResultInterface;

class IssuePatchResult implements ResultInterface
{
    /**
     * @param string $patchUrl  Full URL to the patch file on Drupal.org
     * @param string $patchFileName  Local filename for the downloaded patch
     * @param string $branchName  The issue branch name, e.g. "3383637-schedule_transition"
     * @param string $issueVersionBranch  The base development branch, e.g. "11.x-"
     */
    public function __construct(
        public readonly string $patchUrl,
        public readonly string $patchFileName,
        public readonly string $branchName,
        public readonly string $issueVersionBranch,
    ) {
    }

    public static function fromIssueNodeAndFile(IssueNode $issue, File $file): self
    {
        return new self(
            patchUrl: $file->url,
            patchFileName: $issue->buildCleanTitle() . '.patch',
            branchName: $issue->buildBranchName(),
            issueVersionBranch: $issue->buildIssueVersionBranch(),
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'patch_url' => $this->patchUrl,
            'patch_file_name' => $this->patchFileName,
            'branch_name' => $this->branchName,
            'issue_version_branch' => $this->issueVersionBranch,
        ];
    }
}
