<?php

namespace mglaman\DrupalOrgCli\Formatter;

use mglaman\DrupalOrg\Result\Issue\IssueForkResult;
use mglaman\DrupalOrg\Result\Issue\IssueResult;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestDiffResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestFilesResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestListResult;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestStatusResult;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;
use mglaman\DrupalOrg\Result\Project\ProjectReleasesResult;
use mglaman\DrupalOrg\Result\ResultInterface;

abstract class AbstractFormatter implements FormatterInterface
{
    final public function format(ResultInterface $result): string
    {
        return match (true) {
            $result instanceof IssueResult => $this->formatIssue($result),
            $result instanceof IssueForkResult => $this->formatIssueFork($result),
            $result instanceof ProjectIssuesResult => $this->formatProjectIssues($result),
            $result instanceof MaintainerIssuesResult => $this->formatMaintainerIssues($result),
            $result instanceof ProjectReleasesResult => $this->formatProjectReleases($result),
            $result instanceof MergeRequestListResult => $this->formatMergeRequestList($result),
            $result instanceof MergeRequestStatusResult => $this->formatMergeRequestStatus($result),
            $result instanceof MergeRequestFilesResult => $this->formatMergeRequestFiles($result),
            $result instanceof MergeRequestDiffResult => $this->formatMergeRequestDiff($result),
            default => throw new \InvalidArgumentException(
                sprintf('Unsupported result type: %s', get_class($result))
            ),
        };
    }

    abstract protected function formatIssue(IssueResult $result): string;
    abstract protected function formatIssueFork(IssueForkResult $result): string;
    abstract protected function formatProjectIssues(ProjectIssuesResult $result): string;
    abstract protected function formatMaintainerIssues(MaintainerIssuesResult $result): string;
    abstract protected function formatProjectReleases(ProjectReleasesResult $result): string;
    abstract protected function formatMergeRequestList(MergeRequestListResult $result): string;
    abstract protected function formatMergeRequestStatus(MergeRequestStatusResult $result): string;
    abstract protected function formatMergeRequestFiles(MergeRequestFilesResult $result): string;
    abstract protected function formatMergeRequestDiff(MergeRequestDiffResult $result): string;
}
