<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\Action\Project\GetProjectReleaseNotesAction;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReleaseNotes extends ProjectCommandBase
{
    protected function configure(): void
    {
        $this
          ->setName('project:release-notes')
          ->setAliases(['prn'])
          ->addArgument('project', InputArgument::REQUIRED, 'The project')
          ->addArgument('version', InputArgument::REQUIRED, 'The release version')
          ->setDescription('View release notes for a release');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = (string) $this->stdIn->getArgument('version');
        $action = new GetProjectReleaseNotesAction($this->client);
        try {
            $result = $action($this->projectName, $version);
        } catch (\RuntimeException $e) {
            $this->stdErr->writeln($e->getMessage());
            return 1;
        }
        $this->stdOut->writeln("<options=bold>Release notes for {$this->projectName} {$result->version}</>");
        $this->stdOut->writeln("");
        $this->stdOut->writeln($this->processReleaseNotes($result->body));
        return 0;
    }

    protected function processReleaseNotes(string $body): string
    {
        $body = html_entity_decode($body);
        $body = strip_tags($body, '<p><li>');
        return str_replace(['<p>', '</p>', '<li>', '</li>'], ['', PHP_EOL, '  <options=bold>*</> ', ''], $body);
    }
}
