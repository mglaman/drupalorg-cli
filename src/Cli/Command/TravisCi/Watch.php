<?php

namespace mglaman\DrupalOrgCli\Command\TravisCi;

use GuzzleHttp\Client;
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

    protected function configure()
    {
        $this
          ->setName('travisci:watch')
          ->setAliases(['tci:w'])
          ->addArgument('build', InputArgument::REQUIRED, 'The build ID')
          ->setDescription('Watches a Travis CI job');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $buildId = $this->stdIn->getArgument('build');

        $client = new Client([
          'base_uri' => 'https://api.travis-ci.org/',
          'cookies' => true,
          'headers' => [
            'User-Agent' => 'DrupalOrgCli/0.0.1',
            'Accept' => 'application/json',
            'Accept-Encoding' => '*',
          ]
        ]);
        $build = $this->getBuild($client, $buildId);

        $this->stdOut->writeln("<comment>Watching build $buildId</comment>");

        $progress = new ProgressBar($this->stdOut);
        $progress->start();
        if ($build->state == 'finished') {
            $progress->advance();
        }
        else {
            while ($build->state != 'finished') {
                $progress->advance();
                sleep(60);
                $build = $this->getBuild($client, $buildId);
            }
        }
        $progress->finish();
        $this->sendNotification('TravisCI', "TravisCI build {$buildId} completed");
        $this->stdOut->writeln('');

        $table = new Table($this->stdOut);
        $table->setHeaders([
          'ID',
          'Message',
          'Result',
        ]);

        if ($build->result == 0) {
            $style = 'info';
            $result = 'pass';
        } elseif ($build->result == 1) {
            $style = 'error';
            $result = 'fail';
        } else {
            $style = 'comment';
            $result = 'pending';
        }


        $table->addRow([
          $buildId,
          $build->message,
          "<$style>" . $result . "</$style>",
        ]);

        $table->render();
    }

    protected function getBuild(Client $client, $buildId) {
        $response = $client->request('GET', "builds/$buildId");
        if ($response->getStatusCode() != 200) {
            exit(1);
        }
        return json_decode((string) $response->getBody());
    }
}
