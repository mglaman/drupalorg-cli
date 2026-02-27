<?php

namespace mglaman\DrupalOrgCli\Command\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestDiffAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetDiff extends MrCommandBase
{
    protected function configure(): void
    {
        $this
            ->setName('mr:diff')
            ->setDescription('Show the unified diff for a merge request.')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format: text, json. Defaults to text.',
                'text'
            );
        $this->configureNidAndMrIid();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = (string) ($this->stdIn->getOption('format') ?? 'text');

        $action = new GetMergeRequestDiffAction($this->client, new GitLabClient());
        $result = $action($this->nid, $this->mrIid);

        if ($format === 'json') {
            $this->stdOut->writeln((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        $this->stdOut->writeln($result->diff);
        return 0;
    }
}
