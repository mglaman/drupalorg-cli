<?php

namespace mglaman\DrupalOrgCli\Command\DrupalCi;

use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
            'Job ID',
            'Patch',
            'Status',
            'Result',
        ]);

        foreach ($piftJobs as $job) {
            $patch = $this->getFile($job->file_id);

            if ($job->result == 'pass') {
                $style = 'info';
            } elseif ($job->result == 'fail') {
                $style = 'error';
            } else {
                $style = 'comment';
            }

            $table->addRow([
                $job->job_id,
                $patch->get('name'),
                $job->status,
                "<$style>" . $job->message . "</$style>",
            ]);
        }
        $table->render();
    }

}