<?php

declare(strict_types=1);

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\Action\Project\GetProjectContainsAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Contains extends ProjectCommandBase
{
    protected function configure(): void
    {
        $this
            ->setName('project:contains')
            ->addArgument('project', InputArgument::OPTIONAL, 'Project machine name (auto-detected from git remote if omitted)')
            ->addArgument('sha', InputArgument::OPTIONAL, 'The commit SHA, full or abbreviated')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output options: text, json, md, llm. Defaults to text.',
                'text'
            )
            ->setDescription('Lists the branches and tags that contain a commit.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // Symfony assigns a lone positional argument to "project". Treat it as
        // the SHA and auto-detect the project from the git remote instead.
        $projectArg = $input->getArgument('project');
        $shaArg = $input->getArgument('sha');

        if ($projectArg !== null && $shaArg === null) {
            $input->setArgument('sha', $projectArg);
            $input->setArgument('project', null);
        }

        parent::initialize($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sha = (string) $this->stdIn->getArgument('sha');
        if ($sha === '') {
            $this->stdErr->writeln('<error>The sha argument is required.</error>');
            return 1;
        }

        $action = new GetProjectContainsAction(new GitLabClient());
        try {
            $result = $action($this->projectName, $sha);
        } catch (\RuntimeException $e) {
            $this->stdErr->writeln('<error>' . OutputFormatter::escape($e->getMessage()) . '</error>');
            return 1;
        }

        if ($this->writeFormatted($result, (string) $this->stdIn->getOption('format'))) {
            return 0;
        }

        $this->stdOut->writeln('<options=bold>' . OutputFormatter::escape($result->title) . '</>');
        $this->stdOut->writeln("Commit: {$result->sha}");
        $this->stdOut->writeln("Committed: {$result->committedDate}");
        $this->stdOut->writeln('');
        $this->stdOut->writeln('<options=bold>Branches</>');
        $this->writeList($result->branches);
        $this->stdOut->writeln('');
        $this->stdOut->writeln('<options=bold>Tags (oldest first)</>');
        $this->writeList($result->tags);
        return 0;
    }

    /**
     * @param string[] $items
     */
    private function writeList(array $items): void
    {
        if ($items === []) {
            $this->stdOut->writeln('  None');
            return;
        }
        foreach ($items as $item) {
            $this->stdOut->writeln("  $item");
        }
    }
}
