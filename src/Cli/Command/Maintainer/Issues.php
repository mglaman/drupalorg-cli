<?php

namespace mglaman\DrupalOrgCli\Command\Maintainer;

use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Issues extends Command
{
    protected function configure(): void
    {
        $this
          ->setName('maintainer:issues')
          ->setAliases(['mi'])
          ->addArgument('uid', InputArgument::REQUIRED, 'The user ID')
          ->addArgument('type', InputArgument::OPTIONAL, 'Type of issues: all, rtbc', 'all')
          ->setDescription('Lists issues for a user, based on maintainer.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $feed = \Feed::load($this->getFeedUrl());
        assert(property_exists($feed, 'title'));
        assert(property_exists($feed, 'item'));

        $output->writeln("<info>{$feed->title}</info>");

        $table = new Table($this->stdOut);
        $table->setStyle('symfony-style-guide');
        $table->setHeaders([
          'Project',
          'Title',
        ]);

        $totalItems = count($feed->item);
        $count = 0;
        foreach ($feed->item as $item) {
            $descriptionDom = new \DOMDocument();
            $descriptionDom->loadHTML($item->description);

            $linkParts = parse_url($item->link);
            $pathPaths = array_values(array_filter(explode('/', $linkParts['path'])));

            $table->addRow([
              $pathPaths[1],
              $item->title . PHP_EOL . '<comment>' . $item->link . '</comment>',
            ]);
            $count++;
            if ($count < $totalItems) {
                $table->addRow(new TableSeparator());
            }
        }
        $table->render();

        return 0;
    }

    protected function getIssueValue(\DOMXPath $xpath, string $class): string {
        $nodes = $xpath->query("//div[contains(@class,\"$class\")]//div");
        return $nodes->item(2)->nodeValue;
    }

    protected function getIssueStatus(\DOMXPath $xpath): string {
        $value = $this->getIssueValue($xpath, 'field-name-field-issue-status');

        switch ($value) {
            case 'Active':
                return '<comment>Active</comment>';
            case 'Fixed':
                return '<info>Fixed</info>';
            case 'Needs work':
                return '<error>Needs Work</error>';
            case 'Needs review':
                return '<question>Needs Review</question>';
            case 'Postponed (maintainer needs more info)':
                return '<comment>Postponed [NMI]</comment>';
            case 'Reviewed & tested by the community':
                return '<info>RTBC</info>';
            default:
                return $value;
        }
    }

    protected function getFeedUrl(): string {
        $uid = $this->stdIn->getArgument('uid');
        switch ($this->stdIn->getArgument('type')) {
            case 'rtbc':
                return "https://www.drupal.org/project/user/$uid/feed?status[0]=14";
            case 'all':
            default:
                return "https://www.drupal.org/project/user/$uid/feed";
        }
    }
}
