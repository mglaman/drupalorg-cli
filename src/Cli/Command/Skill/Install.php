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
            ->setDescription('Installs all drupalorg-cli agent skills into .claude/skills/ in the current directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $skillsRootSrc = __DIR__ . '/../../../../skills';
        $cwd = (string) getcwd();
        $skillsRootDest = $cwd . DIRECTORY_SEPARATOR . '.claude' . DIRECTORY_SEPARATOR . 'skills';

        foreach (new \DirectoryIterator($skillsRootSrc) as $skillDir) {
            if ($skillDir->isDot() || !$skillDir->isDir()) {
                continue;
            }
            $result = $this->installSkill(
                $skillDir->getPathname(),
                $skillsRootDest . DIRECTORY_SEPARATOR . $skillDir->getFilename()
            );
            if ($result !== 0) {
                return $result;
            }
        }

        return 0;
    }

    private function installSkill(string $srcDir, string $destDir): int
    {
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            $this->stdErr->writeln(sprintf('<error>Failed to create directory: %s</error>', $destDir));
            return 1;
        }

        $srcFile = $srcDir . DIRECTORY_SEPARATOR . 'SKILL.md';
        $destFile = $destDir . DIRECTORY_SEPARATOR . 'SKILL.md';
        $content = file_get_contents($srcFile);
        if ($content === false) {
            $this->stdErr->writeln(sprintf('<error>Could not read skill source: %s</error>', $srcFile));
            return 1;
        }
        if (file_put_contents($destFile, $content) === false) {
            $this->stdErr->writeln(sprintf('<error>Failed to write skill file: %s</error>', $destFile));
            return 1;
        }
        $this->stdOut->writeln(sprintf('<comment>Skill installed to %s</comment>', $destFile));

        $refSrcDir = $srcDir . DIRECTORY_SEPARATOR . 'references';
        if (!is_dir($refSrcDir)) {
            return 0;
        }

        $refDestDir = $destDir . DIRECTORY_SEPARATOR . 'references';
        if (!is_dir($refDestDir) && !mkdir($refDestDir, 0755, true) && !is_dir($refDestDir)) {
            $this->stdErr->writeln(sprintf('<error>Failed to create directory: %s</error>', $refDestDir));
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

        return 0;
    }
}
