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
        $releases = $this->client->getProjectReleases($project->nid, [
          'field_release_update_status' => 0,
        ])->get('list');
        $table = new Table($this->stdOut);
        $table->setHeaders([
          'Project',
          'Security coverage',
          'Version',
          'Created',
          'Description',
          'Link',
        ]);

        foreach ($releases as $release) {
            $releaseDate = (new \DateTime())->setTimestamp($release->created);
            $now = new \DateTime();
            $difference = $now->diff($releaseDate);
            if ($difference->m <= 1) {
                $format = 'info';
                $message = "OK";
            }
            elseif ($difference->m >= 2 && $difference->m <= 4) {
                $format = 'comment';
                $message = "Release soon";
            }
            else {
                $format = 'error';
                $message = "Release due";
            }

            if ($release->field_release_version_extra !== NULL) {
                $securty = '<error>✗ Not covered</error>';
            }
            else {
                $securty = '<info>✓ Covered</info>';
            }

            $table->addRow([
              $project->title,
              $securty,
              $release->field_release_version,
              "<$format>" . date('M j, Y', $release->created) . " ($message)</$format>",
              $release->field_release_short_description ?: 'Needs short description',
              'https://wwww.drupal.org/project/' . $machineName,
            ]);
        }
        $table->render();
    }

}
