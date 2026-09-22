<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Command\Issue;

use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrgCli\Command\Issue\IssueCommandBase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command that does nothing but expose the state initialize() leaves behind.
 */
final class InspectableIssueCommand extends IssueCommandBase
{
    public function __construct(bool $requiresRepository)
    {
        parent::__construct('test:issue');
        $this->requiresRepository = $requiresRepository;
    }

    protected function configure(): void
    {
        $this->addArgument('nid', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }

    public function nid(): string
    {
        return $this->nid;
    }

    public function workItemRef(): ?WorkItemRef
    {
        return $this->workItemRef;
    }

    public function hasRepository(): bool
    {
        return isset($this->repository);
    }

    public function repositoryPath(): string
    {
        return $this->cwd;
    }
}
