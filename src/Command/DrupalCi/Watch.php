<?php

namespace mglaman\DrupalOrgCli\Command\DrupalCi;

use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Watch extends Command
{
    protected function configure()
    {
        $this
          ->setName('drupalci:watch')
          ->addArgument('job', InputArgument::REQUIRED, 'The job ID')
          ->setDescription('Watches a Drupal CI job');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $jobId = $this->stdIn->getArgument('job');
        $job = $this->client->getPiftJob($jobId);
        $issue = $this->client->getNode($job->get('issue_nid'));

        $this->stdOut->writeln("<comment>" . $issue->get('title') . "</comment>");

        $progress = new ProgressBar($this->stdOut);
        $progress->start();
        if ($job->get('status') == 'complete') {
            $progress->advance();
        }
        else {
            while ($job->get('status') != 'complete') {
                $progress->advance();
                sleep(15);
                $job = $this->client->getPiftJob($jobId);
            }
        }
        $progress->finish();
        $this->stdOut->writeln('');

        $table = new Table($this->stdOut);
        $table->setHeaders([
          'Job ID',
          'Patch',
          'Status',
          'Result',
        ]);
        $patch = $this->client->getFile($job->get('file_id'));

        if ($job->get('result') == 'pass') {
            $style = 'info';
        } elseif ($job->get('result') == 'fail') {
            $style = 'error';
        } else {
            $style = 'comment';
        }


        $table->addRow([
          $job->get('job_id'),
          $patch->get('name'),
          $job->get('status'),
          "<$style>" . $job->get('message') . "</$style>",
        ]);

        $table->render();
    }
}