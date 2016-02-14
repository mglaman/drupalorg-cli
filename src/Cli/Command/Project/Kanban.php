<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\RawResponse;
use mglaman\DrupalOrgCli\BrowserTrait;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Kanban extends Command
{
    use BrowserTrait;

    protected function configure()
    {
        $this
          ->setName('project:kanban')
          ->addArgument('project', InputArgument::REQUIRED, 'The project machine name')
          ->setDescription('Opens project kanban');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $machineName = $this->stdIn->getArgument('project');
        $this->openUrl('https://contribkanban.com/board/' . $machineName, $this->stdErr, $this->stdOut);
    }

}