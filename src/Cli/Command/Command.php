<?php

namespace mglaman\DrupalOrgCli\Command;

use mglaman\DrupalOrg\Client;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class Command extends BaseCommand
{

    /** @var OutputInterface|null */
    protected ?OutputInterface $stdOut;

    /** @var OutputInterface|null */
    protected ?OutputInterface $stdErr;

    /** @var  InputInterface|null */
    protected ?InputInterface $stdIn;

    /** @var bool */
    protected static bool $interactive = false;

    /**
     * @var \mglaman\DrupalOrg\Client
     */
    protected Client $client;

    /**
     * @inheritdoc
     */
    protected function initialize(
        InputInterface $input,
        OutputInterface $output
    ): void {
        $this->stdOut = $output;
        $this->stdErr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput(
        ) : $output;
        $this->stdIn = $input;
        self::$interactive = $input->isInteractive();
        $this->client = new Client();
    }

    protected function debug(string $message): void
    {
        if ($this->stdOut->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $this->stdOut->writeln('<comment>' . $message . '</comment>');
        }
    }

    /**
     * @param array<int, string> $cmd
     *
     * @todo most callers need a refactor.
     */
    protected function runProcess(array $cmd): Process
    {
        $process = new Process($cmd);
        $process->run();
        return $process;
    }
}
