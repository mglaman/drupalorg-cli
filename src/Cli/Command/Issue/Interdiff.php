<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\Issue\GenerateIssueInterdiffAction;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to generate an interdiff text file for an issue.
 */
class Interdiff extends IssueCommandBase
{

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('issue:interdiff')
            ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
            ->setDescription(
                'Generate an interdiff for the issue from committed local changes.'
            );
    }

    /**
     * {@inheritdoc}
     */
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
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $action = new GenerateIssueInterdiffAction($this->client, $this->repository, $this->cwd);
        try {
            $result = $action($this->nid);
        } catch (\RuntimeException $e) {
            $this->stdErr->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return 1;
        }
        $this->stdOut->writeln(sprintf('<comment>Interdiff written to %s</comment>', $result->interdiffPath));
        $this->stdOut->write($result->diffStat);
        return 0;
    }
}
