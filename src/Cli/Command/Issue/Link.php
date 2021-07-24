<?php
/**
 * Created by PhpStorm.
 * User: mglaman
 * Date: 3/1/16
 * Time: 10:44 PM
 */

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrgCli\BrowserTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Link extends IssueCommandBase
{
    use BrowserTrait;

    protected function configure(): void
    {
        $this
          ->setName('issue:link')
          ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
          ->setDescription('Opens an issue');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->openUrl('https://www.drupal.org/node/' . $this->nid, $this->stdErr, $this->stdOut);
        return 0;
    }
}
