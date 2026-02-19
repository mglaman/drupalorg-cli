<?php

namespace mglaman\DrupalOrg\Tests\Command\Issue;

use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrgCli\Command\Issue\IssueCommandBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(IssueCommandBase::class)]
class IssueCommandBaseTest extends TestCase
{
    private ConcreteIssueCommand $command;

    protected function setUp(): void
    {
        $this->command = new ConcreteIssueCommand('test:command');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function versionBranchNameProvider(): array
    {
        return [
            // Traditional Drupal.org branch format.
            'traditional-minor' => ['8.x-1.0', '1234', '8.x-1.x'],
            'traditional-dev' => ['8.x-1.x-dev', '1234', '8.x-1.x'],
            'traditional-rc' => ['8.x-1.0-rc1', '1234', '8.x-1.x'],
            'traditional-major2' => ['8.x-2.0', '1234', '8.x-2.x'],
            // Semantic versioning branch format.
            'semver-patch-wildcard' => ['1.0.0-x', '1234', '1.0.x'],
            'semver-dev' => ['1.0.x-dev', '1234', '1.0.x'],
            'semver-patch' => ['1.0.1', '1234', '1.0.x'],
            'semver-alpha' => ['2.0.0-alpha1', '1234', '2.0.x'],
        ];
    }

    #[DataProvider('versionBranchNameProvider')]
    public function testGetIssueVersionBranchName(
        string $version,
        string $projectId,
        string $expectedBranch
    ): void {
        $issue = new IssueNode(
            nid: '12345',
            title: 'Test issue',
            created: 0,
            changed: 0,
            commentCount: 0,
            fieldIssueVersion: $version,
            fieldIssueStatus: 1,
            fieldIssueCategory: 1,
            fieldIssuePriority: 200,
            fieldIssueComponent: 'Test',
            fieldProjectId: $projectId,
            fieldProjectMachineName: 'test_module',
            bodyValue: null,
            authorId: null,
            fieldIssueFiles: [],
            comments: [],
        );
        self::assertSame($expectedBranch, $this->command->exposeGetIssueVersionBranchName($issue));
    }
}

/**
 * Concrete subclass to expose the protected method under test.
 */
class ConcreteIssueCommand extends IssueCommandBase
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }

    public function exposeGetIssueVersionBranchName(IssueNode $issue): string
    {
        return $this->getIssueVersionBranchName($issue);
    }
}
