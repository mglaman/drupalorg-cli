<?php

namespace mglaman\DrupalOrgCli\Command\Skill;

use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Command
{

    protected function configure(): void
    {
        $this
            ->setName('skill:install')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_OPTIONAL,
                'Destination directory for the skill file.',
                '.claude/skills'
            )
            ->setDescription('Installs the drupalorg-cli agent skill into your project.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $skillSource = dirname(__DIR__, 4) . '/SKILL.md';
        $content = file_get_contents($skillSource);
        if ($content === false) {
            $this->stdErr->writeln(sprintf('<error>Could not read skill source: %s</error>', $skillSource));
            return 1;
        }

        $basePath = (string) $this->stdIn->getOption('path');
        $dir = rtrim($basePath, '/') . '/drupalorg-cli';
        $fullPath = $dir . '/SKILL.md';

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->stdErr->writeln(sprintf('<error>Failed to create directory: %s</error>', $dir));
            return 1;
        }

        if (file_put_contents($fullPath, $content) === false) {
            $this->stdErr->writeln(sprintf('<error>Failed to write skill file: %s</error>', $fullPath));
            return 1;
        }

        $this->stdOut->writeln(sprintf('Skill installed to %s', $fullPath));
        return 0;
    }
}
