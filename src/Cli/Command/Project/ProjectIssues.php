<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\Action\GitLab\ListGitLabIssuesAction;
use mglaman\DrupalOrg\Action\Project\GetProjectIssuesAction;
use mglaman\DrupalOrg\Enum\ProjectIssueCategory;
use mglaman\DrupalOrg\Enum\ProjectIssueType;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectIssues extends ProjectCommandBase
{

    protected function configure(): void
    {
        $this
            ->setName('project:issues')
            ->setAliases(['pi'])
            ->addArgument('project', InputArgument::OPTIONAL, 'project ID')
            ->addArgument(
                'type',
                InputArgument::OPTIONAL,
                'Type of issues: all, rtbc, review',
                'all'
            )
            ->addOption(
                'core',
                null,
                InputOption::VALUE_OPTIONAL,
                'Core version',
                '8.x'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Limit',
                '10'
            )
            ->addOption(
                'category',
                null,
                InputOption::VALUE_OPTIONAL,
                'Issue category: bug, task, feature, support, plan',
                null
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output options: text, json, md, llm. Defaults to text.',
                'text'
            )
            ->setDescription('Lists issues for a project.');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $format = (string) $this->stdIn->getOption('format');
        $limit = (int) $this->stdIn->getOption('limit');

        if (!$this->projectData->hasIssueQueue) {
            $this->stdErr->writeln('<comment>Project uses GitLab work items (no Drupal.org issue queue).</comment>');
            $result = (new ListGitLabIssuesAction())($this->projectData->machineName, limit: $limit);
            if ($this->writeFormatted($result, $format)) {
                return 0;
            }
            $output->writeln("<info>{$result->projectMachineName}</info>");
            $table = new Table($this->stdOut);
            $table->setHeaders(['ID', 'State', 'Title']);
            $issues = $result->issues;
            $count = count($issues);
            for ($i = 0; $i < $count; $i++) {
                $item = $issues[$i];
                $table->addRow([
                    '#' . $item->iid,
                    $item->state,
                    $item->title . PHP_EOL . '<comment>' . $item->webUrl . '</comment>',
                ]);
                if ($i < $count - 1) {
                    $table->addRow(new TableSeparator());
                }
            }
            $table->render();
            return 0;
        }

        $action = new GetProjectIssuesAction($this->client);
        $categoryOption = $this->stdIn->getOption('category');
        $category = $categoryOption !== null ? ProjectIssueCategory::from((string) $categoryOption) : null;
        $result = $action(
            $this->projectData,
            ProjectIssueType::from((string) $this->stdIn->getArgument('type')),
            (string) $this->stdIn->getOption('core'),
            $limit,
            $category
        );

        if ($this->writeFormatted($result, $format)) {
            return 0;
        }

        $output->writeln("<info>{$result->projectTitle}</info>");
        $table = new Table($this->stdOut);
        $table->setHeaders(
            [
                'ID',
                'Status',
                'Title',
            ]
        );

        $issueList = $result->issues;
        $count = count($issueList);
        for ($i = 0; $i < $count; $i++) {
            $item = $issueList[$i];
            $table->addRow(
                [
                    $item->nid,
                    $this->getIssueStatus($item->fieldIssueStatus),
                    $item->title . PHP_EOL . '<comment>https://www.drupal.org/node/' . $item->nid . '</comment>',
                ]
            );
            if ($i < $count - 1) {
                $table->addRow(new TableSeparator());
            }
        }

        $table->render();
        return 0;
    }

    protected function getIssueStatus(int $value): string
    {
        switch ($value) {
            case 1:
                return '<comment>Active</comment>';
            case 2:
                return '<info>Fixed</info>';
            case 13:
                return '<error>Needs Work</error>';
            case 8:
                return '<question>Needs Review</question>';
            case 16:
                return '<comment>Postponed [NMI]</comment>';
            case 14:
                return '<info>RTBC</info>';
            default:
                return (string)$value;
        }
    }
}
