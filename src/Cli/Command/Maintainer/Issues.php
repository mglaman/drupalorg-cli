<?php

namespace mglaman\DrupalOrgCli\Command\Maintainer;

use mglaman\DrupalOrg\Action\Maintainer\GetMaintainerIssuesAction;
use mglaman\DrupalOrg\Enum\MaintainerIssueType;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Issues extends Command
{

    protected function configure(): void
    {
        $this
            ->setName('maintainer:issues')
            ->setAliases(['mi'])
            ->addArgument(
                'user',
                InputArgument::REQUIRED,
                'The username or uid'
            )
            ->addArgument(
                'type',
                InputArgument::OPTIONAL,
                'Type of issues: any, rtbc',
                'any'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output options: text, json, md, llm. Defaults to text.',
                'text'
            )
            ->setDescription('Lists issues for a user, based on maintainer.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $user = $this->stdIn->getArgument('user');

        $action = new GetMaintainerIssuesAction();
        $result = $action($user, MaintainerIssueType::from((string) $this->stdIn->getArgument('type')));

        if ($this->writeFormatted($result, (string) $this->stdIn->getOption('format'))) {
            return 0;
        }

        $output->writeln("<info>{$result->feedTitle}</info>");

        $table = new Table($this->stdOut);
        $table->setStyle('symfony-style-guide');
        $table->setHeaders(
            [
                'Project',
                'Title',
            ]
        );

        $totalItems = count($result->items);
        $count = 0;
        foreach ($result->items as $item) {
            $table->addRow(
                [
                    $item['project'],
                    $item['title'] . PHP_EOL . '<comment>' . $item['link'] . '</comment>',
                ]
            );
            $count++;
            if ($count < $totalItems) {
                $table->addRow(new TableSeparator());
            }
        }
        $table->render();

        return 0;
    }
}
