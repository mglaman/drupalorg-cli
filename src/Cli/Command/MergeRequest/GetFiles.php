<?php

namespace mglaman\DrupalOrgCli\Command\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestFilesAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetFiles extends MrCommandBase
{
    protected function configure(): void
    {
        $this
            ->setName('mr:files')
            ->setDescription('List changed files in a merge request.')
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

        $action = new GetMergeRequestFilesAction($this->client, new GitLabClient());
        $result = $action($this->nid, $this->mrIid);

        if ($format === 'json') {
            $this->stdOut->writeln((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        if ($result->files === []) {
            $this->stdOut->writeln('No changed files found.');
            return 0;
        }

        $table = new Table($this->stdOut);
        $table->setHeaders(['Path', 'New', 'Deleted', 'Renamed']);
        foreach ($result->files as $file) {
            $table->addRow([
                $file['path'],
                (bool) $file['new_file'] ? 'yes' : '',
                (bool) $file['deleted_file'] ? 'yes' : '',
                (bool) $file['renamed_file'] ? 'yes' : '',
            ]);
        }
        $table->render();

        return 0;
    }
}
