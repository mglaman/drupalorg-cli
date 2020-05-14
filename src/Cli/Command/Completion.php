<?php

namespace mglaman\DrupalOrgCli\Command;

use mglaman\DrupalOrgCli\Cache;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Completion extends Command
{
    protected function configure()
    {
        $this
          ->setName('complete')
          ->setDescription('List commands for (Bash) completion');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('list');

        $arguments = [
            '--format' => 'json'
        ];
        $input = new ArrayInput($arguments);
        $output = new BufferedOutput();
        $returnCode = $command->run($input, $output);
        $commandListArray = json_decode($output->fetch(), true);
        $commandsToComplete = [];
        foreach ($commandListArray['commands'] as $commandToAdd) {
            if ($commandToAdd['name'] == $this->getName()) {
                continue;
            }
            $commandsToComplete[] = $commandToAdd['name'];
        }
        $this->stdOut->writeln(implode(' ', $commandsToComplete));
    }
}
