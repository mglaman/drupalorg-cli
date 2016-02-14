<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\RawResponse;
use mglaman\DrupalOrgCli\BrowserTrait;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Link extends Command
{
    use BrowserTrait;

    protected function configure()
    {
        $this
          ->setName('project:link')
          ->addArgument('project', InputArgument::REQUIRED, 'The project machine name')
          ->setDescription('Opens project page');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $machineName = $this->stdIn->getArgument('project');
        $this->openUrl('https://www.drupal.org/project/' . $machineName, $this->stdErr, $this->stdOut);
    }

}