<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\GitLab\RelateWorkItemsAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\IssueLinkType;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Relate extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('issue:relate')
            ->addArgument('ref', InputArgument::REQUIRED, 'Work item ref: NID, project#nid, or full URL')
            ->addArgument('target', InputArgument::REQUIRED, 'Work item to link to: NID, project#nid, or full URL')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Link type: ' . self::linkTypeList() . '.', IssueLinkType::RelatesTo->value)
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format: text, json, md, llm.', 'text')
            ->setDescription('Link two GitLab work items.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ref = (string) $this->stdIn->getArgument('ref');
        $target = (string) $this->stdIn->getArgument('target');
        $type = (string) $this->stdIn->getOption('type');
        $linkType = IssueLinkType::tryFrom($type) ?? throw new \InvalidArgumentException(sprintf(
            'Unknown link type "%s". Expected one of: %s.',
            $type,
            self::linkTypeList(),
        ));

        $action = new RelateWorkItemsAction($this->client, $this->createGitLabClient());
        $result = $action($ref, $target, $linkType);

        $format = (string) $this->stdIn->getOption('format');
        if ($this->writeFormatted($result, $format)) {
            return 0;
        }
        $this->stdOut->writeln(sprintf(
            'Linked %s#%d %s %s#%d.',
            $result->sourceProjectPath,
            $result->sourceIid,
            $result->linkType,
            $result->targetProjectPath,
            $result->targetIid,
        ));
        return 0;
    }

    protected function createGitLabClient(): GitLabClient
    {
        return new GitLabClient();
    }

    private static function linkTypeList(): string
    {
        return implode(', ', array_column(IssueLinkType::cases(), 'value'));
    }
}
