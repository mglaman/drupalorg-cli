<?php

namespace mglaman\DrupalOrgCli\Command\Mcp;

use Composer\InstalledVersions;
use Mcp\Capability\Registry\Container;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Mcp\ToolRegistry;
use mglaman\DrupalOrgCli\Command\Command;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
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

        $stdErr = $this->stdErr;
        $logger = new class ($stdErr) extends AbstractLogger {
            public function __construct(private readonly ?OutputInterface $output)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->output?->writeln(sprintf('[%s] %s', strtoupper((string) $level), $message));
            }
        };

        self::createServer($version, $logger)->run(new StdioTransport());

        return self::SUCCESS;
    }

    /**
     * Creates the MCP server with the tools from ToolRegistry.
     */
    public static function createServer(
        string $version,
        LoggerInterface $logger,
        Client $client = new Client(),
        GitLabClient $gitLabClient = new GitLabClient(),
    ): Server {
        $container = new Container();
        $container->set(Client::class, $client);
        $container->set(GitLabClient::class, $gitLabClient);

        $toolRegistryDir = dirname((string) (new \ReflectionClass(ToolRegistry::class))->getFileName());

        return Server::builder()
            ->setContainer($container)
            ->setDiscovery($toolRegistryDir, ['.'])
            ->setServerInfo('Drupal.org CLI', $version)
            ->setLogger($logger)
            ->build();
    }
}
