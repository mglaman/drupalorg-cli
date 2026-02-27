<?php

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
            ->setDescription('Installs the drupalorg-cli agent skill into .claude/skills/drupalorg-cli/ in the current directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $skillSourceDir = __DIR__ . '/../../../../skills/drupalorg-cli';
        $cwd = (string) getcwd();
        $destDir = $cwd . DIRECTORY_SEPARATOR . '.claude' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'drupalorg-cli';

        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            $this->stdErr->writeln(sprintf('<error>Failed to create directory: %s</error>', $destDir));
            return 1;
        }

        $skillMdSrc = $skillSourceDir . DIRECTORY_SEPARATOR . 'SKILL.md';
        $skillMdDest = $destDir . DIRECTORY_SEPARATOR . 'SKILL.md';
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

        $refSrcDir = $skillSourceDir . DIRECTORY_SEPARATOR . 'references';
        $refDestDir = $destDir . DIRECTORY_SEPARATOR . 'references';
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
                if ($fileInfo->isDot() || !$fileInfo->isFile() || $fileInfo->getExtension() !== 'md') {
                    continue;
                }
                $refSrc = $fileInfo->getPathname();
                $refDest = $refDestDir . DIRECTORY_SEPARATOR . $fileInfo->getFilename();
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
            $this->stdErr->writeln(sprintf(
                '<error>Failed to read skill references directory: %s (%s)</error>',
                $refSrcDir,
                $e->getMessage()
            ));
            return 1;
        }

        // Install the standalone /drupal-work-on-issue skill.
        $woiSrcDir = __DIR__ . '/../../../../skills/drupal-work-on-issue';
        $woiDestDir = $cwd . DIRECTORY_SEPARATOR . '.claude' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'drupal-work-on-issue';

        if (!is_dir($woiDestDir) && !mkdir($woiDestDir, 0755, true) && !is_dir($woiDestDir)) {
            $this->stdErr->writeln(sprintf('<error>Failed to create directory: %s</error>', $woiDestDir));
            return 1;
        }

        $woiSrc = $woiSrcDir . DIRECTORY_SEPARATOR . 'SKILL.md';
        $woiDest = $woiDestDir . DIRECTORY_SEPARATOR . 'SKILL.md';
        $woiContent = file_get_contents($woiSrc);
        if ($woiContent === false) {
            $this->stdErr->writeln(sprintf('<error>Could not read skill source: %s</error>', $woiSrc));
            return 1;
        }
        if (file_put_contents($woiDest, $woiContent) === false) {
            $this->stdErr->writeln(sprintf('<error>Failed to write skill file: %s</error>', $woiDest));
            return 1;
        }
        $this->stdOut->writeln(sprintf('<comment>Skill installed to %s</comment>', $woiDest));

        return 0;
    }
}
