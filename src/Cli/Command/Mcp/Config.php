<?php

namespace mglaman\DrupalOrgCli\Command\Mcp;

use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Config extends Command
{

    protected function configure(): void
    {
        $this
            ->setName('mcp:config')
            ->setDescription('Output the Claude Desktop MCP configuration snippet.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pharPath = \Phar::running(false);
        if ($pharPath === '') {
            $realPath = realpath((string) $_SERVER['argv'][0]);
            $pharPath = $realPath !== false ? $realPath : (string) $_SERVER['argv'][0];
        }

        $config = [
            'mcpServers' => [
                'drupalorg-cli' => [
                    'command' => 'php',
                    'args' => [$pharPath, 'mcp:serve'],
                ],
            ],
        ];
        $this->stdOut->writeln(
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        return self::SUCCESS;
    }
}
