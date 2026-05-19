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
            ->setDescription('Installs drupalorg-cli discovery skills into .claude/skills/ in the current directory.');
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
            $result = $this->installDiscoveryStub(
                $skillDir->getPathname(),
                $skillDir->getFilename(),
                $skillsRootDest . DIRECTORY_SEPARATOR . $skillDir->getFilename()
            );
            if ($result !== 0) {
                return $result;
            }
        }

        return 0;
    }

    private function installDiscoveryStub(string $srcDir, string $skillName, string $destDir): int
    {
        $srcFile = $srcDir . DIRECTORY_SEPARATOR . 'SKILL.md';
        $content = file_get_contents($srcFile);
        if ($content === false) {
            $this->stdErr->writeln(sprintf('<error>Could not read skill source: %s</error>', $srcFile));
            return 1;
        }

        $stub = $this->buildDiscoveryStub($content, $skillName);

        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            $this->stdErr->writeln(sprintf('<error>Failed to create directory: %s</error>', $destDir));
            return 1;
        }

        $destFile = $destDir . DIRECTORY_SEPARATOR . 'SKILL.md';
        if (file_put_contents($destFile, $stub) === false) {
            $this->stdErr->writeln(sprintf('<error>Failed to write skill file: %s</error>', $destFile));
            return 1;
        }
        $this->stdOut->writeln(sprintf('<comment>Skill installed to %s</comment>', $destFile));

        return 0;
    }

    private function buildDiscoveryStub(string $sourceContent, string $skillName): string
    {
        // Extract frontmatter block (name + description for trigger matching).
        // Preserve it verbatim — the description is what Claude Code uses to
        // decide whether to invoke the skill.
        $frontmatter = '';
        if (preg_match('/^---\n(.*?)\n---/s', $sourceContent, $matches)) {
            $frontmatter = $matches[1];
        }

        // Add allowed-tools so agents can call drupalorg without extra prompts.
        if (!str_contains($frontmatter, 'allowed-tools:')) {
            $frontmatter .= "\nallowed-tools: Bash(drupalorg:*), Bash(drupalorg skill:*)";
        }

        $hasReferences = is_dir(__DIR__ . '/../../../../skills/' . $skillName . '/references');
        $fullFlag = $hasReferences ? "\ndrupalorg skill:get {$skillName} --full  # Include reference files" : '';

        return <<<MD
---
{$frontmatter}
---

**Run `drupalorg skill:get {$skillName}` before using this skill.**
This stub does not contain instructions — they are served by the CLI and always reflect the installed version.

```bash
drupalorg skill:get {$skillName}{$fullFlag}
```

MD;
    }
}
