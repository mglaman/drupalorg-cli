<?php

namespace mglaman\DrupalOrg\Action\Issue;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Result\Issue\IssueResult;

class GetIssueAction implements ActionInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    public function __invoke(string $nid): IssueResult
    {
        $issue = $this->client->getNode($nid);
        return IssueResult::fromIssueNode($issue);
    }
}
