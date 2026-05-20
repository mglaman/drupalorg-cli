<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\Action\GitLab\PostWorkItemSlashCommandAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\SlashCommand;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Assign extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('issue:assign')
            ->addArgument('ref', InputArgument::REQUIRED, 'Work item ref: NID, project#nid, or full URL')
            ->addArgument('users', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Usernames to assign (default: me)', ['me'])
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format: text, json, md, llm.', 'text')
            ->setDescription('Post /do:assign on a GitLab work item.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ref = (string) $this->stdIn->getArgument('ref');
        /** @var array<int, string> $users */
        $users = $this->stdIn->getArgument('users');

        $action = new PostWorkItemSlashCommandAction($this->client, new GitLabClient());
        $result = $action($ref, SlashCommand::assign($users));

        $format = (string) $this->stdIn->getOption('format');
        if ($this->writeFormatted($result, $format)) {
            return 0;
        }
        $this->stdOut->writeln(sprintf(
            'Posted %s on %s (note #%d).',
            $result->command,
            $result->workItemUrl(),
            $result->noteId,
        ));
        return 0;
    }
}
