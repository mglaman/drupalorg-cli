<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\GitLab\CreateWorkItemAction;
use mglaman\DrupalOrg\Enum\ProjectIssueCategory;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrgCli\Command\Command;
use mglaman\DrupalOrgCli\Command\ReadsBodyTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Command
{
    use ReadsBodyTrait;

    protected function configure(): void
    {
        $categories = implode(', ', array_map(static fn(ProjectIssueCategory $c) => $c->value, ProjectIssueCategory::cases()));
        $this
            ->setName('issue:create')
            ->addArgument('project', InputArgument::REQUIRED, 'Project machine name (e.g. tool or project/tool)')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Issue title.')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Issue description (Markdown).')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Read the description from a file. Use - to read from stdin.')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, "Issue category: $categories.")
            ->addOption('label', 'l', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Extra GitLab label to add (repeatable), e.g. state::needsReview.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print what would be created without creating it.')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format: text, json, md, llm.', 'text')
            ->setDescription('Create an issue (GitLab work item) for a project.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = (string) $this->stdIn->getArgument('project');
        $title = $this->stdIn->getOption('title');
        if (!is_string($title) || trim($title) === '') {
            throw new \InvalidArgumentException('Pass the issue title with --title.');
        }
        $description = $this->readBody($input, 'issue description', false);

        $categoryOption = $this->stdIn->getOption('category');
        $category = null;
        if ($categoryOption !== null) {
            $category = ProjectIssueCategory::tryFrom((string) $categoryOption);
            if ($category === null) {
                throw new \InvalidArgumentException(sprintf(
                    'Unknown category "%s". Expected one of: %s.',
                    $categoryOption,
                    implode(', ', array_map(static fn(ProjectIssueCategory $c) => $c->value, ProjectIssueCategory::cases())),
                ));
            }
        }
        /** @var string[] $labels */
        $labels = $this->stdIn->getOption('label');
        $dryRun = (bool) $this->stdIn->getOption('dry-run');

        $action = new CreateWorkItemAction($this->client, $this->createGitLabClient());
        $result = $action($project, $title, $description, $category, $labels, $dryRun);

        $format = (string) $this->stdIn->getOption('format');
        if ($this->writeFormatted($result, $format)) {
            return 0;
        }
        if ($result->isDryRun()) {
            $this->stdOut->writeln(sprintf('Dry run: would create an issue in %s.', $result->projectPath));
            $this->stdOut->writeln(sprintf('Title: %s', $result->title));
            $this->stdOut->writeln(sprintf('Labels: %s', $result->labels === [] ? '(none)' : implode(', ', $result->labels)));
            if ($result->description !== '') {
                $this->stdOut->writeln('Description:');
                $this->stdOut->writeln($result->description, OutputInterface::OUTPUT_RAW);
            }
            return 0;
        }
        $this->stdOut->writeln(sprintf('Created %s: %s', $result->ref(), $result->workItemUrl()));
        return 0;
    }

    protected function createGitLabClient(): GitLabClient
    {
        return new GitLabClient();
    }
}
