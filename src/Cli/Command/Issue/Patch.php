<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\Issue\GenerateIssuePatchAction;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Patch extends IssueCommandBase
{

    protected function configure(): void
    {
        $this
            ->setName('issue:patch')
            ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
            ->setDescription(
                'Generate a patch for the issue from committed local changes.'
            );
    }

    protected function initialize(
        InputInterface $input,
        OutputInterface $output
    ): void {
        parent::initialize($input, $output);
        if ($this->nid !== $this->getNidFromBranch($this->repository)) {
            throw new \InvalidArgumentException(
                'NID from argument is different from NID in issue branch name.'
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $action = new GenerateIssuePatchAction($this->client, $this->repository, $this->cwd);
        try {
            $result = $action($this->nid);
        } catch (\RuntimeException $e) {
            $this->stdErr->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return 1;
        }
        $this->stdOut->writeln(sprintf('<comment>Patch written to %s</comment>', $result->patchPath));
        $this->stdOut->write($result->diffStat);
        return 0;
    }
}
