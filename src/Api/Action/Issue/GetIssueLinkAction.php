<?php

namespace mglaman\DrupalOrg\Action\Issue;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Result\Issue\IssueLinkResult;

class GetIssueLinkAction implements ActionInterface
{
    public function __invoke(string $nid): IssueLinkResult
    {
        return new IssueLinkResult(url: 'https://www.drupal.org/node/' . $nid);
    }
}
