<?php
/**
 * Created by PhpStorm.
 * User: mglaman
 * Date: 3/1/16
 * Time: 10:44 PM
 */

namespace mglaman\DrupalOrgCli\Command\Issue;


use mglaman\DrupalOrgCli\BrowserTrait;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Link extends Command
{
    use BrowserTrait;

    protected function configure()
    {
        $this
          ->setName('issue:link')
          ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID')
          ->setDescription('Opens an issue');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $nid = $this->stdIn->getArgument('nid');
        $this->openUrl('https://www.drupal.org/node/' . $nid, $this->stdErr, $this->stdOut);
    }
}