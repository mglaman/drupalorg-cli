<?php

namespace mglaman\DrupalOrgCli\Command\MergeRequest;

use mglaman\DrupalOrg\GitLab\MergeRequestRef;
use mglaman\DrupalOrgCli\Command\Command;
use mglaman\DrupalOrgCli\Command\Issue\IssueCommandBase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class MrCommandBase extends IssueCommandBase
{
    protected bool $requiresRepository = false;

    protected int $mrIid;

    protected ?MergeRequestRef $mrRef = null;

    protected function configureNidAndMrIid(): void
    {
        $this
            ->addArgument('nid', InputArgument::OPTIONAL, 'The issue NID, project-path!iid (quote in zsh), or GitLab MR URL')
            ->addArgument('mr-iid', InputArgument::OPTIONAL, 'The merge request IID');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $nidArg = (string) $input->getArgument('nid');
        $ref = $nidArg !== '' ? MergeRequestRef::tryParse($nidArg) : null;

        if ($ref !== null) {
            // Skip IssueCommandBase NID resolution — go straight to Command::initialize.
            Command::initialize($input, $output);
            $this->mrRef = $ref;
            $this->nid = '';

            if ($ref->mrIid !== null) {
                $this->mrIid = $ref->mrIid;
            } else {
                $mrIidArg = $input->getArgument('mr-iid');
                if ($mrIidArg === null || $mrIidArg === '') {
                    throw new \RuntimeException('Argument mr-iid is required when project-path has no !iid.');
                }
                $this->mrIid = (int) $mrIidArg;
            }
            return;
        }

        parent::initialize($input, $output);

        $mrIid = $this->stdIn->getArgument('mr-iid');
        if ($mrIid === null || $mrIid === '') {
            throw new \RuntimeException('Argument mr-iid is required.');
        }
        $this->mrIid = (int) $mrIid;
    }
}
