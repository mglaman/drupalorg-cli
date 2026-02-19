<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\Request;
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
        $version = $this->stdIn->getArgument('version');
        $rawData = $this->client->requestRaw(new Request('node.json', [
              'field_release_project' => $this->projectData->nid,
              'field_release_version' => $version,
          ]));
        $releaseList = (array) ($rawData->list ?? []);

        if ($releaseList === [] && preg_match('/^[0-9]+\.[0-9]+\.0$/', $version)) {
            $versionParts = explode('.', $version);
            $version = '8.x-' . $versionParts[0] . '.' . $versionParts[1];

            $this->debug("Trying old, non-semver version string format.");
            $rawData = $this->client->requestRaw(new Request('node.json', [
                'field_release_project' => $this->projectData->nid,
                'field_release_version' => $version,
            ]));
            $releaseList = (array) ($rawData->list ?? []);
        }

        if ($releaseList === []) {
            $this->stdErr->writeln("No release found for $version.");
            exit(1);
        }

        $this->stdOut->writeln("<options=bold>Release notes for {$this->projectName} $version</>");
        $this->stdOut->writeln("");
        $this->stdOut->writeln($this->processReleaseNotes($releaseList[0]->body->value));
        return 0;
    }

    protected function processReleaseNotes(string $body): string
    {
        $body = html_entity_decode($body);
        $body = strip_tags($body, '<p><li>');
        return str_replace(['<p>', '</p>', '<li>', '</li>'], ['', PHP_EOL, '  <options=bold>*</> ', ''], $body);
    }
}
