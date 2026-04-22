<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\Entity\Project;
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

        if (!$this->stdIn->hasArgument('project')) {
            $this->debug("Argument project not provided. Trying to get it from the remote URL of the current repository.");
            $this->projectName = $this->getProjectFromRemote();
            if ($this->projectName === '') {
                $this->stdErr->writeln("Failed to find project / machine name from current Git repository.");
                exit(1);
            }
        } else {
            $projectName = $this->stdIn->getArgument('project') ?? '';
            $this->projectName = $projectName;
        }

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
     * Gets project from remote origin name.
     *
     * @return string
     *   The project name.
     */
    protected function getProjectFromRemote(): string
    {
        $process = new Process((array) 'git config --get remote.origin.url');
        $process->run();
        $remote_url = trim($process->getOutput());
        preg_match('#.*\/(.*)\.git$#', $remote_url, $matches);
        return $matches[1] ?? '';
    }
}
