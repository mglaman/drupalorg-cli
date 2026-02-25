<?php

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Result\ResultInterface;

class IssueBranchResult implements ResultInterface
{
    public function __construct(
        public readonly string $branchName,
        public readonly string $issueVersionBranch,
    ) {
    }

    public static function fromIssueNode(IssueNode $issue): self
    {
        $cleanTitle = preg_replace('/[^a-zA-Z0-9]+/', '_', $issue->title);
        $cleanTitle = strtolower(substr((string) $cleanTitle, 0, 20));
        $cleanTitle = (string) preg_replace('/(^_|_$)/', '', $cleanTitle);

        $branchName = sprintf('%s-%s', $issue->nid, $cleanTitle);

        $issueVersionBranch = $issue->fieldIssueVersion;
        if ($issue->fieldProjectId === '3060') {
            $issueVersionBranch = substr($issueVersionBranch, 0, 5);
        } elseif (preg_match('/^(\d+\.\d+)\./', $issueVersionBranch, $matches)) {
            $issueVersionBranch = $matches[1] . '.x';
        } else {
            $issueVersionBranch = substr($issueVersionBranch, 0, 6) . 'x';
        }

        return new self(
            branchName: $branchName,
            issueVersionBranch: $issueVersionBranch,
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
