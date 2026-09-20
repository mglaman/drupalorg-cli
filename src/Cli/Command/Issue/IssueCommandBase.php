<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
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

    protected ?WorkItemRef $workItemRef = null;

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

        $nidArg = (string) $this->stdIn->getArgument('nid');
        $ref = $nidArg !== '' ? WorkItemRef::tryParse($nidArg) : null;
        if ($ref !== null) {
            $this->workItemRef = $ref;
            $this->nid = (string) $ref->issueId;
            if ($this->requiresRepository) {
                $this->initRepo();
            }
            return;
        }

        $this->nid = $nidArg;
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
     * The project named by the nid argument's qualifier, if one was given.
     */
    protected function explicitProjectMachineName(): ?string
    {
        return $this->workItemRef?->projectMachineName();
    }

    /**
     * Initializes repository for current directory.
     *
     * @throws \RuntimeException
     *   When the current directory is not inside a git repository. The console
     *   application renders the message and exits non-zero.
     */
    protected function initRepo(): void
    {
        if (isset($this->repository)) {
            $this->debug("Repository already initialized.");
            return;
        }

        $process = new Process(['git', 'rev-parse', '--show-toplevel']);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException('No repository found in current directory.');
        }

        try {
            $this->cwd = trim($process->getOutput());
            $this->repository = (new Git())->open($this->cwd);
        } catch (\Exception $e) {
            throw new \RuntimeException('No repository found in current directory.', 0, $e);
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
