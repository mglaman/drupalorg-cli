<?php

namespace mglaman\DrupalOrgCli\Command\TravisCi;

use GuzzleHttp\Client;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ListBuilds extends Command
{
    protected function configure(): void
    {
        $this
          ->setName('travisci:list')
          ->setAliases(['tci:l'])
          ->addArgument('slug', InputArgument::REQUIRED, 'The project slug')
          ->setDescription('Lists Travis Ci builds for a Drupal project');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $slug = $this->stdIn->getArgument('slug');
        assert(is_string($slug));
        $project = $this->slugMapping($slug);
        $this->debug("TravisCI slug: $project");

        $client = new Client([
          'base_uri' => 'https://api.travis-ci.org/',
          'cookies' => true,
          'headers' => [
            'User-Agent' => 'DrupalOrgCli/0.0.1',
            'Accept' => 'application/json',
            'Accept-Encoding' => '*',
          ]
        ]);

        $response = $client->request('GET', "repos/$project/builds");
        if ($response->getStatusCode() != 200) {
            exit(1);
        }
        $builds = json_decode((string) $response->getBody());

        $this->stdOut->writeln("<comment>Builds for " . $project . "</comment>");

        $table = new Table($this->stdOut);
        $table->setHeaders([
          'ID',
          'Message',
          'Status',
          'Result',
        ]);

        $jobRunning = null;

        foreach ($builds as $build) {
            if ($build->result == 0) {
                $style = 'info';
                $result = 'pass';
            } elseif ($build->result == 1) {
                $style = 'error';
                $result = 'fail';
            } else {
                $style = 'comment';
                $result = 'pending';
                $jobRunning = $build->id;
            }

            $table->addRow([
              $build->id,
              $build->message,
              $build->state,
              "<$style>" . $result . "</$style>",
            ]);
        }
        $table->render();

        if ($jobRunning) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                "Test #{$jobRunning} is running, do you want to watch it? [Yes]",
                ['Yes', 'No'],
                0
            );
            $answer = $helper->ask($this->stdIn, $this->stdOut, $question);

            if ($answer == 'Yes') {
                $command = $this->getApplication()->find('travisci:watch');
                $this->stdIn = new ArgvInput([
                  'application' => 'drupalorgcli',
                  'command' => 'drupalci:watch',
                  'build' => $jobRunning,
                ]);
                $command->run($this->stdIn, $this->stdOut);
            }
        }

        return 0;
    }

    protected function slugMapping(string $project): string
    {
        $maps = [
            'commerce' => 'drupalcommerce/commerce',
            'commerce_kickstart' => 'commerceguys/commerce_kickstart',
        ];

        return $maps[$project] ?? $project;
    }
}
