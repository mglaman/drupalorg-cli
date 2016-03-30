<?php

namespace mglaman\DrupalOrgCli\Command\Maintainer;


use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Issues extends Command
{
    protected function configure()
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
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $feed = \Feed::load($this->getFeedUrl());

        $output->writeln("<info>{$feed->title}</info>");

        $table = new Table($this->stdOut);
        $table->setHeaders([
          'Project',
          'Version',
          'Status',
          'Title',
        ]);

        foreach ($feed->item as $item) {
            $descriptionDom = new \DOMDocument();
            $descriptionDom->loadHTML($item->description);

            $descriptionXpath = new \DOMXPath($descriptionDom);

            $table->addRow([
              $this->getIssueValue($descriptionXpath, 'field-name-field-project'),
              $this->getIssueValue($descriptionXpath, 'field-name-field-issue-version'),
              $this->getIssueStatus($descriptionXpath),
              $item->title . PHP_EOL . '<comment>' . $item->link . '</comment>',
            ]);
            $table->addRow(new TableSeparator());
        }

        $table->render();
    }

    protected function getIssueValue(\DOMXPath $xpath, $class) {
        $nodes = $xpath->query("//div[contains(@class,\"$class\")]//div");
        return $nodes->item(2)->nodeValue;
    }

    protected function getIssueStatus(\DOMXPath $xpath) {
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

    protected function getFeedUrl() {
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
