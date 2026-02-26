<?php

namespace mglaman\DrupalOrgCli\Command\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestLogsAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetLogs extends MrCommandBase
{
    protected function configure(): void
    {
        $this
            ->setName('mr:logs')
            ->setDescription('Show failed job traces from the latest pipeline for a merge request.')
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

        $action = new GetMergeRequestLogsAction($this->client, new GitLabClient());
        $result = $action($this->nid, $this->mrIid);

        if ($format === 'json') {
            $this->stdOut->writeln((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        if ($result->pipelineId === null) {
            $this->stdOut->writeln('No pipeline found for this merge request.');
            return 0;
        }

        if ($result->failedJobs === []) {
            $this->stdOut->writeln(sprintf('Pipeline #%d: no failed jobs.', $result->pipelineId));
            return 0;
        }

        foreach ($result->failedJobs as $job) {
            $this->stdOut->writeln(sprintf('=== Failed job: %s ===', $job['name']));
            $this->stdOut->writeln($job['trace_excerpt']);
            $this->stdOut->writeln('');
        }

        return 0;
    }
}
