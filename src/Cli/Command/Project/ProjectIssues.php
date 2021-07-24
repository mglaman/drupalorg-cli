<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\Request;
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
                'Type of issues: all, rtbc',
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
        $options = [
            'field_release_project' => $this->projectData->nid,
            'type' => 'project_release',
            'sort' => 'nid',
            'direction' => 'DESC',
            'limit' => 100,
        ];
        $releases = $this->client->request(new Request('node.json', $options))
            ->getList();

        $api_params = [
            'type' => 'project_issue',
            'field_project' => $this->projectData->nid,
            'field_issue_status[value]' => [1, 8, 13, 14, 16],
            'sort' => 'field_issue_priority',
            'direction' => 'DESC',
            'limit' => $this->stdIn->getOption('limit'),
        ];

        switch ($this->stdIn->getArgument('type')) {
            case 'rtbc':
                $api_params['field_issue_status[value]'] = [14];
                break;
            case 'review':
                $api_params['field_issue_status[value]'] = [8];
                break;
            default:
                $api_params['field_issue_status[value]'] = [1, 8, 13, 14, 16];
        }

        foreach ($releases as $release) {
            if (strpos(
                $release->field_release_version,
                $this->stdIn->getOption('core')
            ) === 0) {
                $api_params['field_issue_version']['value'][] = $release->field_release_version;
            }
        }

        $issues = $this->client->request(new Request('node.json', $api_params));

        $output->writeln("<info>{$this->projectData->title}</info>");
        $table = new Table($this->stdOut);
        $table->setHeaders(
            [
                'ID',
                'Status',
                'Title',
            ]
        );

        $list = $issues->getList();
        $iterator = $list->getIterator();
        while ($iterator->valid()) {
            $item = $iterator->current();
            $table->addRow(
                [
                    $item->nid,
                    $this->getIssueStatus((int)$item->field_issue_status),
                    $item->title . PHP_EOL . '<comment>https://www.drupal.org/node/' . $item->nid . '</comment>',
                ]
            );
            $iterator->next();

            if ($iterator->valid()) {
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
