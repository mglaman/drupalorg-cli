<?php

declare(strict_types=1);

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\Issue\SearchIssuesAction;
use mglaman\DrupalOrgCli\Command\Project\ProjectCommandBase;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Search extends ProjectCommandBase
{
    private const STATUS_MAP = [
        'all' => [],
        'open' => [1, 8, 13, 14, 16],
        'closed' => [2, 3, 4, 5, 6, 7],
        'rtbc' => [14],
        'review' => [8],
    ];

    protected function configure(): void
    {
        $this
            ->setName('issue:search')
            ->setAliases(['is'])
            ->addArgument('project', InputArgument::OPTIONAL, 'Project machine name (auto-detected from git remote if omitted)')
            ->addArgument('query', InputArgument::OPTIONAL, 'Search text to filter issue titles')
            ->addOption(
                'status',
                's',
                InputOption::VALUE_OPTIONAL,
                'Issue status filter: all, open, closed, rtbc, review',
                'all'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Maximum number of results',
                '20'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output options: text, json, md, llm. Defaults to text.',
                'text'
            )
            ->setDescription('Searches issues for a project by title keyword.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // If only one positional argument is provided, Symfony assigns it to
        // the first argument ("project"). Detect this case: when "query" is
        // empty, treat the "project" value as the query and auto-detect the
        // project from the git remote.
        $projectArg = $input->getArgument('project');
        $queryArg = $input->getArgument('query');

        if ($projectArg !== null && $queryArg === null) {
            $input->setArgument('query', $projectArg);
            $input->setArgument('project', null);
        }

        parent::initialize($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $query = (string) $this->stdIn->getArgument('query');
        if ($query === '') {
            $this->stdErr->writeln('<error>The query argument is required.</error>');
            return 1;
        }

        $status = (string) $this->stdIn->getOption('status');
        $statuses = self::STATUS_MAP[$status] ?? self::STATUS_MAP['open'];

        $action = new SearchIssuesAction($this->client);
        $result = $action(
            $this->projectData,
            $query,
            $statuses,
            (int) $this->stdIn->getOption('limit')
        );

        if ($this->writeFormatted($result, (string) $this->stdIn->getOption('format'))) {
            return 0;
        }

        $output->writeln("<info>{$result->projectTitle}</info> — search: <comment>{$query}</comment>");
        $table = new Table($this->stdOut);
        $table->setHeaders(['ID', 'Status', 'Title']);

        $issueList = $result->issues;
        $count = count($issueList);
        for ($i = 0; $i < $count; $i++) {
            $item = $issueList[$i];
            $table->addRow([
                $item->nid,
                $this->getIssueStatus($item->fieldIssueStatus),
                $item->title . PHP_EOL . '<comment>https://www.drupal.org/node/' . $item->nid . '</comment>',
            ]);
            if ($i < $count - 1) {
                $table->addRow(new TableSeparator());
            }
        }

        $table->render();
        return 0;
    }

    protected function getIssueStatus(int $value): string
    {
        return match ($value) {
            1 => '<comment>Active</comment>',
            2 => '<info>Fixed</info>',
            13 => '<error>Needs Work</error>',
            8 => '<question>Needs Review</question>',
            16 => '<comment>Postponed [NMI]</comment>',
            14 => '<info>RTBC</info>',
            default => (string) $value,
        };
    }
}
