<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\ProjectRemote;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class ProjectCommandBase extends Command
{

    /**
     * The project entity.
     *
     * @var \mglaman\DrupalOrg\Entity\Project
     */
    protected Project $projectData;

    /**
     * The project machine name.
     *
     * @var string
     */
    protected string $projectName;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $projectName = $this->stdIn->getArgument('project');
        if (!is_string($projectName) || $projectName === '') {
            $this->debug("Argument project not provided. Trying to get it from the remote URL of the current repository.");
            $remote = ProjectRemote::tryParse($this->getRemoteUrl());
            if ($remote === null) {
                $this->stdErr->writeln("Could not determine the project from the git remote; pass the machine name as an argument.");
                exit(1);
            }
            $projectName = $remote->machineName;
        }
        $this->projectName = $projectName;

        // The kanban and link command doesn't need the project data from drupal.org,
        // but checking that the project exists makes sense for all project commands.
        $project = $this->client->getProject($this->projectName);
        if ($project === null) {
            $this->stdErr->writeln("Project $this->projectName not found.");
            exit(1);
        }

        $this->projectData = $project;
    }

    /**
     * Gets the origin remote URL of the current repository.
     *
     * @return string
     *   The remote URL, or an empty string when there is no origin remote.
     */
    protected function getRemoteUrl(): string
    {
        $process = new Process(['git', 'config', '--get', 'remote.origin.url']);
        $process->run();
        return trim($process->getOutput());
    }
}
