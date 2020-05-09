<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class ProjectCommandBase extends Command
{

  /**
   * The project machine name.
   *
   * @var string
   */
    protected $projectName;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->projectName = $this->stdIn->getArgument('project');
        if (empty($this->projectName)) {
            $this->debug("Argument project not provided. Trying to get it from the remote URL of the current repository.");
            $this->projectName = $this->getProjectFromRemote();
            if (empty($this->projectName)) {
                $this->stdErr->writeln("Failed to find project / machine name from current Git repository.");
                exit(1);
            }
        }
    }

  /**
   * Gets project from remote origin name.
   *
   * @return string
   *   The project name.
   */
    protected function getProjectFromRemote()
    {
        $process = new Process('git config --get remote.origin.url');
        $process->run();
        $remote_url = trim($process->getOutput());
        preg_match('#.*\/(.*)\.git$#', $remote_url, $matches);
        return $matches[1];
    }
}
