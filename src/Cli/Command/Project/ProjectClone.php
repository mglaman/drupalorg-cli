<?php

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrg\GitlabClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Process\Process;

class ProjectClone extends ProjectCommandBase
{

    protected function configure(): void
    {
        $this
            ->setName('project:clone')
            ->setAliases(['pc'])
            ->addArgument('project', InputArgument::REQUIRED, 'project ID')
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Branch to clone'
            )
            ->addOption(
                'method',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Method for cloning (ssh or https)',
                'ssh'
            )
            ->setDescription('Clone a project\'s repository.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $method = mb_strtolower($this->stdIn->getOption('method'));
        if (!in_array($method, ['ssh', 'https'], true)) {
            $this->stdErr->writeln('<error>Unexpected method ' . $method . '.</error>');
            $this->stdErr->writeln('Allowed values are: ssh, https');
            return 1;
        }

        $gitlabClient = new GitlabClient();
        $project = $gitlabClient->getProject($this->projectName);
        $branches = $gitlabClient->getProjectBranches($project->id);
        $defaultBranch = reset($branches);

        $branch = $this->stdIn->getOption('branch');
        if (null === $branch) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                "Which branch do you want to clone? [$defaultBranch]",
                $branches,
                $defaultBranch
            );
            $branch = $helper->ask($input, $output, $question);
        }
        if (!in_array($branch, $branches, true)) {
            $this->stdErr->writeln('<error>Unexpected branch ' . $branch . '.</error>');
            $this->stdErr->writeln('Existing branches are: ' . implode(', ', $branches));
            return 2;
        }

        $cloneUrl = $method === 'ssh' ? $project->ssh_url_to_repo : $project->http_url_to_repo;

        // Clone the repository.
        $process = new Process(['git', 'clone', '--branch', $branch, $cloneUrl]);
        $process->run();
        if (!$process->isSuccessful()) {
            $this->stdErr->writeln('<error>The clone process failed.</error>');
            $this->stdErr->writeln($process->getErrorOutput());
            return 3;
        }

        $this->stdOut->writeln('<info>Clone successful!</info>');
        $this->stdOut->writeln("<comment>$ cd $this->projectName</comment>");
        return 0;
    }
}
