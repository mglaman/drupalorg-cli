<?php

namespace mglaman\DrupalOrgCli\Command\Mcp;

use Composer\InstalledVersions;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Serve extends Command
{

    protected function configure(): void
    {
        $this
            ->setName('mcp:serve')
            ->setDescription('Start a Model Context Protocol server over stdio.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $version = InstalledVersions::getPrettyVersion('mglaman/drupalorg-cli') ?? '0.0.0';
        } catch (\OutOfBoundsException $e) {
            $version = '0.0.0';
        }

        $server = Server::builder()
            ->setDiscovery(
                dirname(__DIR__, 4),
                ['src/Api/Mcp']
            )
            ->setServerInfo('Drupal.org CLI', $version)
            ->build();

        $server->run(new StdioTransport());

        return self::SUCCESS;
    }
}
