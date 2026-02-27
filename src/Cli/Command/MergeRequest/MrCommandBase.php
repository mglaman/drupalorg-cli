<?php

namespace mglaman\DrupalOrgCli\Command\MergeRequest;

use mglaman\DrupalOrgCli\Command\Issue\IssueCommandBase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class MrCommandBase extends IssueCommandBase
{
    protected bool $requiresRepository = false;

    protected int $mrIid;

    protected function configureNidAndMrIid(): void
    {
        $this
            ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
            ->addArgument('mr-iid', InputArgument::REQUIRED, 'The merge request IID');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->mrIid = (int) $this->stdIn->getArgument('mr-iid');
    }
}
