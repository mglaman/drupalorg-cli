<?php

namespace mglaman\DrupalOrg\Action\Issue;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Result\Issue\IssueBranchResult;

class GetIssueBranchNameAction implements ActionInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    public function __invoke(string $nid): IssueBranchResult
    {
        $issue = $this->client->getNode($nid);
        return IssueBranchResult::fromIssueNode($issue);
    }
}
