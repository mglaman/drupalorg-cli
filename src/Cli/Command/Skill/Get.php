<?php

declare(strict_types=1);

namespace mglaman\DrupalOrgCli\Command\Skill;

use mglaman\DrupalOrg\Action\Skill\ListSkillsAction;
use mglaman\DrupalOrg\Result\Skill\SkillItem;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
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
            ->setDescription('Outputs current skill content for agent consumption. Lists available skills when no name is given.')
            ->addArgument('name', InputArgument::OPTIONAL, 'Skill name (e.g. drupalorg-cli). Omit to list available skills.')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Include reference files')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output options for the skill list: text, json, md, llm. Defaults to text.',
                'text'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if ($name === null || $name === '') {
            return $this->listSkills((string) $input->getOption('format'));
        }
        $name = (string) $name;

        $skillFile = ListSkillsAction::DEFAULT_SKILLS_ROOT . '/' . $name . '/SKILL.md';

        if (!is_file($skillFile)) {
            $this->stdErr->writeln(sprintf('<error>Skill not found: %s</error>', $name));
            $available = array_map(
                static fn(SkillItem $skill) => $skill->name,
                (new ListSkillsAction())()->skills
            );
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
            $refDir = ListSkillsAction::DEFAULT_SKILLS_ROOT . '/' . $name . '/references';
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

    private function listSkills(string $format): int
    {
        $result = (new ListSkillsAction())();

        if ($this->writeFormatted($result, $format)) {
            return 0;
        }

        $table = new Table($this->stdOut);
        $table->setHeaders(['Skill', 'Description']);
        $table->setColumnMaxWidth(1, 80);
        foreach ($result->skills as $skill) {
            $table->addRow([$skill->name, $skill->description]);
        }
        $table->render();
        $this->stdOut->writeln('');
        $this->stdOut->writeln('Run: drupalorg skill:get <name>');

        return 0;
    }
}
