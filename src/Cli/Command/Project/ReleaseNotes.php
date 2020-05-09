<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\Request;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReleaseNotes extends ProjectCommandBase
{
    protected function configure()
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $project = $this->getProject($this->projectName)->get('list')[0];
        $version = $this->stdIn->getArgument('version');
        $release = $this->client->request(new Request('node.json', [
              'field_release_project' => $project->nid,
              'field_release_version' => $version,
          ]))
          ->get('list');

        $this->stdOut->writeln("<options=bold>Release notes for {$this->projectName} $version</>");
        $this->stdOut->writeln("");
        $this->stdOut->writeln($this->processReleaseNotes($release[0]->body->value));
    }

    protected function processReleaseNotes($body)
    {
        $body = html_entity_decode($body);
        $body = strip_tags($body, '<p><li>');
        $body = str_replace(['<p>', '</p>', '<li>', '</li>'], ['', PHP_EOL, '  <options=bold>*</> ', ''], $body);
        return $body;
    }
}
