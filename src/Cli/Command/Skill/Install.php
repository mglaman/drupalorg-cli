<?php

declare(strict_types=1);

namespace mglaman\DrupalOrgCli\Command\Skill;

use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('skill:install')
            ->setDescription('Installs the drupalorg-cli discovery skill into .claude/skills/ in the current directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $srcFile = __DIR__ . '/../../../../skills/drupalorg-cli/SKILL.md';
        $cwd = (string) getcwd();
        $destDir = $cwd . DIRECTORY_SEPARATOR . '.claude' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'drupalorg-cli';
        $destFile = $destDir . DIRECTORY_SEPARATOR . 'SKILL.md';

        $content = file_get_contents($srcFile);
        if ($content === false) {
            $this->stdErr->writeln(sprintf('<error>Could not read skill source: %s</error>', $srcFile));
            return 1;
        }

        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            $this->stdErr->writeln(sprintf('<error>Failed to create directory: %s</error>', $destDir));
            return 1;
        }

        if (file_put_contents($destFile, $content) === false) {
            $this->stdErr->writeln(sprintf('<error>Failed to write skill file: %s</error>', $destFile));
            return 1;
        }

        $this->stdOut->writeln(sprintf('<comment>Skill installed to %s</comment>', $destFile));

        return 0;
    }
}
