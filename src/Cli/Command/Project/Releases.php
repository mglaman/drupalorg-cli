<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\RawResponse;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Releases extends ProjectCommandBase
{
    protected function configure()
    {
        $this
          ->setName('project:releases')
          ->setAliases(['pr'])
          ->addArgument('project', InputArgument::OPTIONAL, 'The project machine name')
          ->setDescription('Lists available releases');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $releases = $this->client->getProjectReleases($this->projectData->nid, [
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

        $release_versions = [];
        foreach ($releases as $release) {
            $releaseDate = (new \DateTime())->setTimestamp($release->created);
            $now = new \DateTime();
            $difference = $now->diff($releaseDate);
            if ($difference->m <= 1) {
                $format = 'info';
                $message = "OK";
            } elseif ($difference->m >= 2 && $difference->m <= 4) {
                $format = 'comment';
                $message = "Release soon";
            } else {
                $format = 'error';
                $message = "Release due";
            }

            if ($release->field_release_version_extra !== null) {
                $securty = '<error>✗ Not covered</error>';
            } else {
                $securty = '<info>✓ Covered</info>';
            }

            $table->addRow([
              $this->projectData->title,
              $securty,
              $release->field_release_version,
              "<$format>" . date('M j, Y', $release->created) . " ($message)</$format>",
              $release->field_release_short_description ?? 'Needs short description',
              'https://www.drupal.org/project/' . $this->projectName,
            ]);
            $release_versions[$release->field_release_version] = '';
        }
        $table->render();

        $release_versions['cancel'] = '';

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            "View release notes? [cancel]",
            $release_versions,
            'cancel'
        );
        $answer = $helper->ask($this->stdIn, $this->stdOut, $question);
        if ($answer != 'cancel') {
            $command = $this->getApplication()->find('project:release-notes');
            $sub_input = new ArgvInput([
            'application' => 'drupalorgcli',
            'command' => 'drupalci:release-notes',
            'project' => $this->projectName,
            'version' => $answer,
            ]);
            $command->run($sub_input, $this->stdOut);
        }
    }
}
