<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Command\Issue;

use mglaman\DrupalOrgCli\Command\Issue\IssueCommandBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

/**
 * Runs a throwaway command through initialize() inside a temporary directory,
 * with or without a git repository in it, and asserts on the state it leaves.
 */
#[CoversClass(IssueCommandBase::class)]
class IssueCommandBaseTest extends TestCase
{
    private const WORK_ITEM_URL = 'https://git.drupalcode.org/project/restrict_route_by_ip/-/work_items/3617735';

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
        self::removeDirectory($this->workDir);
    }

    private function initGitRepository(string $branch): void
    {
        $this->git('init', '--quiet');
        $this->git('checkout', '--quiet', '-b', $branch);
        $this->git(
            '-c',
            'user.name=Test',
            '-c',
            'user.email=test@example.com',
            '-c',
            'commit.gpgsign=false',
            'commit',
            '--quiet',
            '--allow-empty',
            '--message',
            'Initial commit',
        );
    }

    private function git(string ...$arguments): void
    {
        (new Process(['git', ...$arguments], $this->workDir))->mustRun();
    }

    private static function removeDirectory(string $directory): void
    {
        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }

    private static function command(bool $requiresRepository): InspectableIssueCommand
    {
        return new InspectableIssueCommand($requiresRepository);
    }

    /**
     * @param array<string, string> $arguments
     */
    private static function execute(InspectableIssueCommand $command, array $arguments): void
    {
        (new CommandTester($command))->execute($arguments);
    }

    public function testBareNidOpensRepository(): void
    {
        $this->initGitRepository('main');
        $command = self::command(requiresRepository: true);

        self::execute($command, ['nid' => '3617735']);

        self::assertSame('3617735', $command->nid());
        self::assertNull($command->workItemRef());
        self::assertTrue($command->hasRepository());
        self::assertSame(realpath($this->workDir), $command->repositoryPath());
    }

    public function testProjectQualifiedNidOpensRepository(): void
    {
        $this->initGitRepository('main');
        $command = self::command(requiresRepository: true);

        self::execute($command, ['nid' => 'restrict_route_by_ip#3617735']);

        self::assertSame('3617735', $command->nid());
        self::assertSame('restrict_route_by_ip', $command->workItemRef()?->projectMachineName());
        self::assertTrue($command->hasRepository());
    }

    public function testWorkItemUrlOpensRepository(): void
    {
        $this->initGitRepository('main');
        $command = self::command(requiresRepository: true);

        self::execute($command, ['nid' => self::WORK_ITEM_URL]);

        self::assertSame('3617735', $command->nid());
        self::assertSame('project/restrict_route_by_ip', $command->workItemRef()?->projectPath);
        self::assertTrue($command->hasRepository());
    }

    public function testProjectQualifiedNidSkipsGitWhenNoRepositoryIsRequired(): void
    {
        $command = self::command(requiresRepository: false);

        self::execute($command, ['nid' => 'restrict_route_by_ip#3617735']);

        self::assertSame('3617735', $command->nid());
        self::assertSame('restrict_route_by_ip', $command->workItemRef()?->projectMachineName());
        self::assertFalse($command->hasRepository());
    }

    public function testBareNidSkipsGitWhenNoRepositoryIsRequired(): void
    {
        $command = self::command(requiresRepository: false);

        self::execute($command, ['nid' => '3617735']);

        self::assertSame('3617735', $command->nid());
        self::assertFalse($command->hasRepository());
    }

    public function testReadsNidFromBranchName(): void
    {
        $this->initGitRepository('3617735-fix_js');
        $command = self::command(requiresRepository: true);

        self::execute($command, []);

        self::assertSame('3617735', $command->nid());
        self::assertTrue($command->hasRepository());
    }

    public function testFailsWithoutNidOnAnUnnumberedBranch(): void
    {
        $this->initGitRepository('main');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Argument nid not provided and not able to get it from current branch name.');
        self::execute(self::command(requiresRepository: true), []);
    }

    public function testFailsOutsideARepository(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No repository found in current directory.');
        self::execute(self::command(requiresRepository: true), ['nid' => '3617735']);
    }
}
