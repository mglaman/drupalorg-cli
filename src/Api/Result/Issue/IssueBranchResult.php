<?php

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Result\ResultInterface;

class IssueBranchResult implements ResultInterface
{
    /**
     * @param string $branchName  The issue branch name, e.g. "3383637-schedule_transition"
     * @param string $issueVersionBranch  The base development branch, e.g. "11.x-"
     */
    public function __construct(
        public readonly string $branchName,
        public readonly string $issueVersionBranch,
    ) {
    }

    public static function fromIssueNode(IssueNode $issue): self
    {
        return new self(
            branchName: $issue->buildBranchName(),
            issueVersionBranch: $issue->buildIssueVersionBranch(),
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'branch_name' => $this->branchName,
            'issue_version_branch' => $this->issueVersionBranch,
        ];
    }
}
