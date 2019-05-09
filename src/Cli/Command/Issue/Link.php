<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrgCli\BrowserTrait;
use mglaman\DrupalOrgCli\Command\Command;
use mglaman\DrupalOrgCli\Git;
use mglaman\DrupalOrgCli\IssueNidArgumentTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Link extends Command
{
    use BrowserTrait;
    use IssueNidArgumentTrait;

    /**
     * {@inheritdoc}
     *
     */
    protected function configure()
    {
        $this
          ->setName('issue:link')
          ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
          ->setDescription('Opens an issue. If no `nid` is provided, one is parsed from the current branch.');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nid = $this->getNidArgument($this->stdIn);
        if ($nid === null) {
            $this->stdOut->writeln('Please provide an issue nid');
            return 1;
        }
        $issue = $this->getNode($nid);
        return $this->openUrl($issue->get('url'), $this->stdErr, $this->stdOut);
    }
}
