<?php

namespace mglaman\DrupalOrg\Action\Issue;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrg\IssueBranchNaming;
use mglaman\DrupalOrg\MigratedIssueException;
use mglaman\DrupalOrg\Result\Issue\IssueBranchResult;

class GetIssueBranchNameAction implements ActionInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    /**
     * @param WorkItemRef|null $ref
     *   Names the GitLab work item directly. Without it the Drupal.org node
     *   is read, and a node that moved to GitLab is followed there.
     */
    public function __invoke(string $nid, ?WorkItemRef $ref = null): IssueBranchResult
    {
        if ($ref === null) {
            try {
                return IssueBranchResult::fromIssueNode($this->client->getNode($nid));
            } catch (MigratedIssueException $e) {
                $ref = $e->ref;
            }
        }

        $issue = GitLabIssue::fromStdClass($this->gitLabClient->getIssue($ref->projectPath, $ref->issueId));
        $versionBranch = IssueBranchNaming::versionBranchFromLabels($issue->labels)
            ?? (string) $this->gitLabClient->getProject($ref->projectPath)->default_branch;

        return IssueBranchResult::fromGitLabIssue($issue, $versionBranch);
    }
}
