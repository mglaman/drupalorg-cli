<?php

namespace mglaman\DrupalOrgCli\Command;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Result\ResultInterface;
use mglaman\DrupalOrgCli\Formatter\JsonFormatter;
use mglaman\DrupalOrgCli\Formatter\LlmFormatter;
use mglaman\DrupalOrgCli\Formatter\MarkdownFormatter;
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
        $noCache = $input->hasOption('no-cache') && (bool) $input->getOption('no-cache');
        $this->client = new Client($noCache);
    }

    protected function debug(string $message): void
    {
        if ($this->stdOut->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $this->stdOut->writeln('<comment>' . $message . '</comment>');
        }
    }

    protected function writeFormatted(ResultInterface $result, string $format): bool
    {
        if ($format === 'text') {
            return false;
        }
        $formatter = match ($format) {
            'json' => new JsonFormatter(),
            'md', 'markdown' => new MarkdownFormatter(),
            'llm' => new LlmFormatter(),
            default => throw new \InvalidArgumentException("Unknown format: $format"),
        };
        $this->stdOut->writeln($formatter->format($result));
        return true;
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
