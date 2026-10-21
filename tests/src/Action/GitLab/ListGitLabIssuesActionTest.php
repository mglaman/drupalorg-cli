<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Action\GitLab;

use mglaman\DrupalOrg\Action\GitLab\ListGitLabIssuesAction;
use mglaman\DrupalOrg\Enum\ProjectIssueCategory;
use mglaman\DrupalOrg\Enum\ProjectIssueType;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\GitLab\GitLabIssuesResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListGitLabIssuesAction::class)]
#[CoversClass(GitLabIssuesResult::class)]
#[CoversClass(ProjectIssueType::class)]
#[CoversClass(ProjectIssueCategory::class)]
class ListGitLabIssuesActionTest extends TestCase
{
    /**
     * @return iterable<string, array{ProjectIssueType, ?ProjectIssueCategory, ?string}>
     */
    public static function filterProvider(): iterable
    {
        yield 'no filters' => [ProjectIssueType::All, null, null];
        yield 'rtbc' => [ProjectIssueType::Rtbc, null, 'state::rtbc'];
        yield 'review' => [ProjectIssueType::Review, null, 'state::needsReview'];
        yield 'category only' => [ProjectIssueType::All, ProjectIssueCategory::Bug, 'category::bug'];
        yield 'type and category' => [ProjectIssueType::Rtbc, ProjectIssueCategory::Task, 'state::rtbc,category::task'];
    }

    #[DataProvider('filterProvider')]
    public function testFiltersMapToGitLabLabels(
        ProjectIssueType $type,
        ?ProjectIssueCategory $category,
        ?string $expectedLabels,
    ): void {
        $expectedParams = [
            'state' => 'opened',
            'per_page' => 10,
            'order_by' => 'created_at',
            'sort' => 'desc',
        ];
        if ($expectedLabels !== null) {
            $expectedParams['labels'] = $expectedLabels;
        }

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('getIssues')
            ->with('project/smart_content', $expectedParams)
            ->willReturn([]);

        $result = (new ListGitLabIssuesAction($gitLabClient))(
            'smart_content',
            limit: 10,
            type: $type,
            category: $category,
        );

        self::assertSame('smart_content', $result->projectMachineName);
        self::assertSame([], $result->issues);
    }
}
