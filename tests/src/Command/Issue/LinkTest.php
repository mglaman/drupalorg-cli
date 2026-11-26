<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Command\Issue;

use mglaman\DrupalOrgCli\Command\Issue\Link;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(Link::class)]
class LinkTest extends TestCase
{
    private string $originalCwd;

    private string $workDir;

    protected function setUp(): void
    {
        $this->originalCwd = (string) getcwd();
        $this->workDir = sys_get_temp_dir() . '/drupalorg-cli-' . uniqid();
        mkdir($this->workDir);
        chdir($this->workDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        rmdir($this->workDir);
    }

    public function testOpensIssueOutsideGitRepository(): void
    {
        // Print the URL instead of launching a browser.
        $command = new class extends Link {
            protected function openUrl(string $url, OutputInterface $stdErr, OutputInterface $stdOut): void
            {
                $stdOut->writeln($url);
            }
        };
        $tester = new CommandTester($command);

        $tester->execute(['nid' => '3617735']);

        $tester->assertCommandIsSuccessful();
        self::assertSame("https://www.drupal.org/i/3617735\n", $tester->getDisplay());
    }
}
