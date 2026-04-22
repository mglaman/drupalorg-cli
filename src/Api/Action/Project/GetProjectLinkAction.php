<?php

namespace mglaman\DrupalOrg\Action\Project;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Result\Project\ProjectLinkResult;

class GetProjectLinkAction implements ActionInterface
{
    public function __invoke(string $machineName): ProjectLinkResult
    {
        return new ProjectLinkResult(url: 'https://www.drupal.org/project/' . $machineName);
    }
}
