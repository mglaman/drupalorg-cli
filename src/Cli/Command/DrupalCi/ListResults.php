<?php

namespace mglaman\DrupalOrgCli\Command\DrupalCi;

use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class ListResults extends Command
{
    protected function configure()
    {
        $this
          ->setName('drupalci:list')
          ->setAliases(['ci:l'])
          ->addArgument('nid', InputArgument::REQUIRED, 'The issue node ID')
          ->setDescription('Lists test results for an issue');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $issueNid = $this->stdIn->getArgument('nid');
        $issue = $this->getNode($issueNid);
        $piftJobs = $this->getPiftJobs([
            'issue_nid' => $issueNid,
        ])->get('list');

        $this->stdOut->writeln("<comment>" . $issue->get('title') . "</comment>");

        $table = new Table($this->stdOut);
        $table->setHeaders([
            'Updated',
            'Job ID',
            'Patch',
            'Status',
            'Result',
            'CI URL',
        ]);

        $jobRunning = null;

        foreach ($piftJobs as $job) {
            $patch = $this->getFile($job->file_id);

            if ($job->result == 'pass') {
                $style = 'info';
            } elseif ($job->result == 'fail') {
                $style = 'error';
            } else {
                $style = 'comment';
            }

            if ($job->status == 'running') {
                $jobRunning = $job->job_id;
            }

            $table->addRow([
                date('M j, Y - H:i:s', $job->updated),
                $job->job_id,
                $patch->get('name'),
                $job->status,
                "<$style>" . $job->message . "</$style>",
                $job->ci_url,
            ]);
        }
        $table->render();

        if ($jobRunning) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
              "Test #{$jobRunning} is running, do you want to watch it? [Yes]",
              ['Yes' => '', 'No' => ''],
              0
            );
            $answer = $helper->ask($this->stdIn, $this->stdOut, $question);

            if ($answer == 'Yes') {
                $command = $this->getApplication()->find('drupalci:watch');
                $this->stdIn = new ArgvInput([
                    'application' => 'drupalorgcli',
                    'command' => 'drupalci:watch',
                    'job' => $jobRunning,
                ]);
                $command->run($this->stdIn, $this->stdOut);
            }
        }
    }

}
