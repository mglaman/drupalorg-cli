<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\RawResponse;
use mglaman\DrupalOrgCli\Command\Command;

abstract class IssueCommandBase extends Command
{

  /**
   * Get the issue version's branch name.
   *
   * @param \mglaman\DrupalOrg\RawResponse $issue
   *   The issue raw response
   *
   * @return string
   *   The branch name.
   */
    protected function getIssueVersionBranchName(RawResponse $issue): string
    {
        $issue_version_branch = $issue->get('field_issue_version');
        if ($issue->get('field_project')->id === '3060') {
            return substr($issue_version_branch, 0, 5);
        }
        // Issue versions can be 8.x-1.0-rc1, 8.x-1.x-dev, 8.x-2.0. So we get the
        // first section to find the development branch. This will give us a
        // branch in the format of: 8.x-1.x, for example.
        return substr($issue_version_branch, 0, 6) . 'x';
    }

    /**
     * Gets a clean version of the issue title.
     *
     * @param \mglaman\DrupalOrg\RawResponse $issue
     *   The issue raw response.
     *
     * @return string
     *   The formatted title.
     */
    protected function getCleanIssueTitle(RawResponse $issue): string
    {
        $cleanTitle = preg_replace('/[^a-zA-Z0-9]+/', '_', $issue->get('title'));
        $cleanTitle = strtolower(substr($cleanTitle, 0, 20));
        $cleanTitle = preg_replace('/(^_|_$)/', '', $cleanTitle);
        return $cleanTitle;
    }

    /**
     * Builds a branch name for an issue.
     *
     * @param \mglaman\DrupalOrg\RawResponse $issue
     *   The raw response.
     *
     * @return string
     *   The branch name.
     */
    protected function buildBranchName(RawResponse $issue): string
    {
        $cleanTitle = $this->getCleanIssueTitle($issue);
        return sprintf('%s-%s', $issue->get('nid'), $cleanTitle);
    }
}
