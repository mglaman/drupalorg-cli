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
                'Destination directory for the skill directory (SKILL.md + references/).',
                '.claude/skills'
            )
            ->setDescription('Installs the drupalorg-cli agent skill into your project.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $skillSourceDir = __DIR__ . '/../../../../skills/drupalorg-cli';

        $basePath = trim((string) $this->stdIn->getOption('path'));
        if ($basePath === '') {
            $this->stdErr->writeln('<error>The --path option must not be empty.</error>');
            return 1;
        }
        $normalizedBase = rtrim($basePath, '/\\');
        if ($normalizedBase === '' || (strlen($normalizedBase) === 2 && ctype_alpha($normalizedBase[0]) && $normalizedBase[1] === ':')) {
            $this->stdErr->writeln('<error>The --path option must not point to a filesystem root.</error>');
            return 1;
        }
        $destDir = $normalizedBase . DIRECTORY_SEPARATOR . 'drupalorg-cli';

        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            $this->stdErr->writeln(sprintf('<error>Failed to create directory: %s</error>', $destDir));
            return 1;
        }

        $skillMdSrc = $skillSourceDir . '/SKILL.md';
        $skillMdDest = $destDir . '/SKILL.md';
        $content = file_get_contents($skillMdSrc);
        if ($content === false) {
            $this->stdErr->writeln(sprintf('<error>Could not read skill source: %s</error>', $skillMdSrc));
            return 1;
        }
        if (file_put_contents($skillMdDest, $content) === false) {
            $this->stdErr->writeln(sprintf('<error>Failed to write skill file: %s</error>', $skillMdDest));
            return 1;
        }
        $this->stdOut->writeln(sprintf('<comment>Skill installed to %s</comment>', $skillMdDest));

        $refSrcDir = $skillSourceDir . '/references';
        $refDestDir = $destDir . '/references';
        if (!is_dir($refDestDir) && !mkdir($refDestDir, 0755, true) && !is_dir($refDestDir)) {
            $this->stdErr->writeln(sprintf('<error>Failed to create directory: %s</error>', $refDestDir));
            return 1;
        }

        if (!is_dir($refSrcDir) || !is_readable($refSrcDir)) {
            $this->stdErr->writeln(sprintf('<error>Skill references directory is missing or not readable: %s</error>', $refSrcDir));
            return 1;
        }

        try {
            foreach (new \DirectoryIterator($refSrcDir) as $fileInfo) {
                if ($fileInfo->isDot() || $fileInfo->getExtension() !== 'md') {
                    continue;
                }
                $refSrc = $fileInfo->getPathname();
                $refDest = $refDestDir . '/' . $fileInfo->getFilename();
                if (!copy($refSrc, $refDest)) {
                    $lastError = error_get_last();
                    $errorDetail = (is_array($lastError) && $lastError['message'] !== '')
                        ? ' Underlying error: ' . $lastError['message']
                        : '';
                    $this->stdErr->writeln(sprintf(
                        '<error>Failed to copy reference file from %s to %s.%s</error>',
                        $refSrc,
                        $refDest,
                        $errorDetail
                    ));
                    return 1;
                }
                $this->stdOut->writeln(sprintf('<comment>Reference installed to %s</comment>', $refDest));
            }
        } catch (\UnexpectedValueException $e) {
            $this->stdErr->writeln(sprintf('<error>Failed to read skill references directory: %s</error>', $refSrcDir));
            return 1;
        }

        return 0;
    }
}
