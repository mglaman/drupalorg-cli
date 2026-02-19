<?php

namespace mglaman\DrupalOrgCli\Command\DrupalCi;

use mglaman\DrupalOrgCli\Command\Command;
use mglaman\DrupalOrgCli\NotificationTrait;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Watch extends Command
{
    use NotificationTrait;

    protected function configure(): void
    {
        $this
          ->setName('drupalci:watch')
          ->setAliases(['ci:w'])
          ->addArgument('job', InputArgument::REQUIRED, 'The job ID')
          ->setDescription('Watches a Drupal CI job');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobId = $this->stdIn->getArgument('job');
        $job = $this->client->getPiftJob($jobId);
        $issue = $this->client->getNode($job->issueNid);

        $this->stdOut->writeln("<comment>" . $issue->title . "</comment>");

        $progress = new ProgressBar($this->stdOut);
        $progress->start();
        if ($job->status === 'complete') {
            $progress->advance();
        } else {
            while ($job->status !== 'complete') {
                $progress->advance();
                sleep(60);
                $job = $this->client->getPiftJob($jobId);
            }
        }
        $progress->finish();

        $result = $job->result;

        if ($result === 'pass') {
            $suffix = 'passed';
            $icon = '';
        } elseif ($result === 'fail') {
            $suffix = 'failed';
            $icon = '';
        } else {
            $suffix = 'completed';
            $icon = '';
        }
        $this->sendNotification('DrupalCI', "Job #{$jobId} {$suffix}", $icon);
        $this->stdOut->writeln('');

        $table = new Table($this->stdOut);
        $table->setHeaders([
          'Job ID',
          'Patch',
          'Status',
          'Result',
        ]);
        $patch = $this->client->getFile($job->fileId);

        if ($result === 'pass') {
            $style = 'info';
        } elseif ($result === 'fail') {
            $style = 'error';
        } else {
            $style = 'comment';
        }


        $table->addRow([
          $job->jobId,
          $patch->name,
          $job->status,
          "<$style>" . $job->message . "</$style>",
        ]);

        $table->render();
        return 0;
    }
}
