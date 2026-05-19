<?php

declare(strict_types=1);

namespace mglaman\DrupalOrgCli\Command\Skill;

use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Get extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('skill:get')
            ->setDescription('Outputs current skill content for agent consumption.')
            ->addArgument('name', InputArgument::REQUIRED, 'Skill name (e.g. drupalorg-cli)')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Include reference files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $skillFile = __DIR__ . '/../../../../skill-data/' . $name . '/SKILL.md';

        if (!is_file($skillFile)) {
            $this->stdErr->writeln(sprintf('<error>Skill not found: %s</error>', $name));
            $available = $this->getAvailableSkills();
            if ($available !== []) {
                $this->stdErr->writeln('Available skills: ' . implode(', ', $available));
            }
            return 1;
        }

        $content = file_get_contents($skillFile);
        if ($content === false) {
            $this->stdErr->writeln(sprintf('<error>Could not read skill: %s</error>', $name));
            return 1;
        }

        $this->stdOut->write($content);

        if ((bool) $input->getOption('full')) {
            $refDir = __DIR__ . '/../../../../skill-data/' . $name . '/references';
            if (is_dir($refDir)) {
                foreach (new \DirectoryIterator($refDir) as $fileInfo) {
                    if ($fileInfo->isDot() || !$fileInfo->isFile() || $fileInfo->getExtension() !== 'md') {
                        continue;
                    }
                    $refContent = file_get_contents($fileInfo->getPathname());
                    if ($refContent !== false) {
                        $this->stdOut->writeln('');
                        $this->stdOut->writeln('---');
                        $this->stdOut->writeln('## Reference: ' . $fileInfo->getBasename('.md'));
                        $this->stdOut->writeln('---');
                        $this->stdOut->writeln('');
                        $this->stdOut->write($refContent);
                    }
                }
            }
        }

        return 0;
    }

    /**
     * @return string[]
     */
    private function getAvailableSkills(): array
    {
        $skillsRoot = __DIR__ . '/../../../../skill-data';
        if (!is_dir($skillsRoot)) {
            return [];
        }
        $skills = [];
        foreach (new \DirectoryIterator($skillsRoot) as $dir) {
            if ($dir->isDot() || !$dir->isDir()) {
                continue;
            }
            $skills[] = $dir->getFilename();
        }
        sort($skills);
        return $skills;
    }
}
