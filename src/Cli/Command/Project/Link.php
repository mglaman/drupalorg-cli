<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\Action\Project\GetProjectLinkAction;
use mglaman\DrupalOrgCli\BrowserTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Link extends ProjectCommandBase
{
    use BrowserTrait;

    protected function configure(): void
    {
        $this
          ->setName('project:link')
          ->addArgument('project', InputArgument::OPTIONAL, 'The project machine name')
          ->setDescription('Opens project page');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = new GetProjectLinkAction();
        $result = $action($this->projectName);
        $this->openUrl($result->url, $this->stdErr, $this->stdOut);
        return 0;
    }
}
