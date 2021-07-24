<?php

namespace mglaman\DrupalOrgCli\Command;

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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $this->getApplication()->find('list');

        $arguments = [
            '--format' => 'json'
        ];

        $buffered_output = new BufferedOutput();
        $command->run(new ArrayInput($arguments), $buffered_output);
        $commandListArray = json_decode(
            $buffered_output->fetch(),
            TRUE,
            512,
            JSON_THROW_ON_ERROR
        );
        $commandsToComplete = [];
        foreach ($commandListArray['commands'] as $commandToAdd) {
            if ($commandToAdd['name'] == $this->getName()) {
                continue;
            }
            $commandsToComplete[] = $commandToAdd['name'];
        }
        $this->stdOut->writeln(implode(' ', $commandsToComplete));
        return 0;
    }
}
