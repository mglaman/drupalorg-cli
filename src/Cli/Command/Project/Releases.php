<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\RawResponse;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Releases extends Command
{
    protected function configure()
    {
        $this
          ->setName('project:releases')
          ->addArgument('project', InputArgument::REQUIRED, 'The project machine name')
          ->setDescription('Lists available releases');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $machineName = $this->stdIn->getArgument('project');
        $project = $this->getProject($machineName)->getList()->offsetGet(0);
        $releases = $this->client->getProjectReleases($project->nid)
          ->get('list');

        $this->stdOut->writeln("<comment>" . $project->title . "</comment>");
        $table = new Table($this->stdOut);
        $table->setHeaders([
          'Version',
          'Created',
            'Description',
          'ID',
        ]);

        foreach ($releases as $release) {
            $table->addRow([
              $release->field_release_version,
              date('M j, Y', $release->changed),
                $release->field_release_short_description,
              $release->nid,
            ]);
        }
        $table->render();
    }

}