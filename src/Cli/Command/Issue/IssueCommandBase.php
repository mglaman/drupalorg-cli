<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class IssueCommandBase extends Command
{

    protected ?GitRepository $repository;

    /**
     * The current working directory.
     *
     * @var string
     */
    protected string $cwd;

    /**
     * The issue node ID.
     *
     * @var string
     */
    protected string $nid;

    /**
     * Whether this command requires a git repository.
     * When false, initRepo() is skipped if nid is provided as an argument.
     */
    protected bool $requiresRepository = true;

    protected function initialize(
        InputInterface $input,
        OutputInterface $output
    ): void {
        parent::initialize($input, $output);

        $this->nid = (string) $this->stdIn->getArgument('nid');
        if ($this->nid === '') {
            $this->debug(
                "Argument nid not provided. Trying to get it from current branch name."
            );
            $this->initRepo();
            $this->nid = $this->getNidFromBranch($this->repository);
            if ($this->nid === '') {
                throw new \RuntimeException(
                    'Argument nid not provided and not able to get it from current branch name.'
                );
            }
        } elseif ($this->requiresRepository) {
            $this->initRepo();
        }
    }

    /**
     * Initializes repository for current directory.
     */
    protected function initRepo(): void
    {
        if (isset($this->repository)) {
            $this->debug("Repository already initialized.");
            return;
        }


        try {
            $process = new Process(['git', 'rev-parse', '--show-toplevel']);
            $process->run();
            $repository_dir = trim($process->getOutput());
            $this->cwd = $repository_dir;
            $client = new Git();
            $this->repository = $client->open($this->cwd);
        } catch (\Exception $e) {
            $this->stdErr->writeln("No repository found in current directory.");
            exit(1);
        }
    }

    /**
     * Gets nid from head / branch name.
     *
     * @param \CzProject\GitPhp\GitRepository $repo
     *   The repository.
     *
     * @return string
     *   The node id.
     */
    protected function getNidFromBranch(GitRepository $repo): string
    {
        $branch = $repo->getCurrentBranchName();
        return (preg_match('/(\d+)-/', $branch, $matches) ? $matches[1] : '');
    }
}
