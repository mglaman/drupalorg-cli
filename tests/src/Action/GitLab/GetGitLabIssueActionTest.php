<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Action\GitLab;

use mglaman\DrupalOrg\Action\GitLab\GetGitLabIssueAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\Entity\GitLabNote;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrg\Result\GitLab\GitLabIssueResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetGitLabIssueAction::class)]
#[CoversClass(GitLabIssueResult::class)]
#[CoversClass(GitLabNote::class)]
class GetGitLabIssueActionTest extends TestCase
{
    private static function makeIssue(): \stdClass
    {
        return (object) [
            'iid' => 3586157,
            'title' => 'Example AI context issue',
            'description' => 'Body',
            'state' => 'opened',
            'labels' => ['state::needsReview'],
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-02T00:00:00Z',
            'web_url' => 'https://git.drupalcode.org/project/ai_context/-/work_items/3586157',
            'author' => (object) ['username' => 'reporter'],
            'assignees' => [],
        ];
    }

    /**
     * @return \stdClass[]
     */
    private static function makeNotes(): array
    {
        return [
            (object) [
                'id' => 1,
                'body' => 'added label state::needsReview',
                'system' => true,
                'author' => (object) ['username' => 'drupalorg-bot'],
                'created_at' => '2025-01-01T01:00:00Z',
            ],
            (object) [
                'id' => 4,
                'body' => 'Fork created: issue/ai_context-3586157',
                'system' => false,
                'author' => (object) ['username' => 'drupalbot'],
                'created_at' => '2025-01-01T01:30:00Z',
            ],
            (object) [
                'id' => 2,
                'body' => 'Reviewed the approach, looks good.',
                'system' => false,
                'author' => (object) ['username' => 'reviewer'],
                'created_at' => '2025-01-01T02:00:00Z',
            ],
            (object) [
                'id' => 3,
                'body' => 'Addressed feedback.',
                'author' => (object) ['name' => 'Contributor Name'],
                'created_at' => '2025-01-01T03:00:00Z',
            ],
        ];
    }

    private static function makeRef(): WorkItemRef
    {
        $ref = WorkItemRef::tryParse('ai_context#3586157');
        self::assertNotNull($ref);
        return $ref;
    }

    public function testWithoutCommentsSkipsNotesRequest(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssue')->willReturn(self::makeIssue());
        $gitLabClient->expects(self::never())->method('getIssueNotes');

        $result = (new GetGitLabIssueAction($gitLabClient))(self::makeRef());

        self::assertSame(3586157, $result->issue->iid);
        self::assertSame([], $result->comments);
        self::assertSame([], $result->jsonSerialize()['comments']);
    }

    public function testWithCommentsDropsSystemAndBotNotes(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssue')->willReturn(self::makeIssue());
        $gitLabClient->expects(self::once())
            ->method('getIssueNotes')
            ->with('project/ai_context', 3586157)
            ->willReturn(self::makeNotes());

        $result = (new GetGitLabIssueAction($gitLabClient))(self::makeRef(), true);

        self::assertCount(2, $result->comments);
        self::assertSame('reviewer', $result->comments[0]->author);
        self::assertSame(['reviewer', 'Contributor Name'], array_map(static fn(GitLabNote $n) => $n->author, $result->comments));
        self::assertSame('Reviewed the approach, looks good.', $result->comments[0]->body);
        self::assertSame('Contributor Name', $result->comments[1]->author);

        $json = $result->jsonSerialize();
        self::assertSame(
            [
                'id' => 2,
                'body' => 'Reviewed the approach, looks good.',
                'author' => 'reviewer',
                'created_at' => '2025-01-01T02:00:00Z',
            ],
            $json['comments'][0]
        );
    }

    public function testIncludeBotCommentsKeepsBotNotesButNotSystemNotes(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssue')->willReturn(self::makeIssue());
        $gitLabClient->method('getIssueNotes')->willReturn(self::makeNotes());

        $result = (new GetGitLabIssueAction($gitLabClient))(self::makeRef(), true, true);

        self::assertSame(
            ['drupalbot', 'reviewer', 'Contributor Name'],
            array_map(static fn(GitLabNote $n) => $n->author, $result->comments)
        );
    }
}
