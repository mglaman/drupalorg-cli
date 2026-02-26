<?php

namespace mglaman\DrupalOrgCli\Command\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestStatusAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetStatus extends MrCommandBase
{
    protected function configure(): void
    {
        $this
            ->setName('mr:status')
            ->setDescription('Show the pipeline status for a merge request.')
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

        $action = new GetMergeRequestStatusAction($this->client, new GitLabClient());
        $result = $action($this->nid, $this->mrIid);

        if ($format === 'json') {
            $this->stdOut->writeln((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        $this->stdOut->writeln(sprintf('MR !%d pipeline status: <info>%s</info>', $result->iid, $result->status));
        if ($result->pipelineId !== null) {
            $this->stdOut->writeln(sprintf('Pipeline ID: %d', $result->pipelineId));
        }
        if ($result->pipelineUrl !== null && $result->pipelineUrl !== '') {
            $this->stdOut->writeln(sprintf('Pipeline URL: %s', $result->pipelineUrl));
        }

        return 0;
    }
}
