<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Action\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\Entity\GitLabNote;
use mglaman\DrupalOrg\GitLab\MergeRequestRef;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetMergeRequestAction::class)]
#[CoversClass(MergeRequestResult::class)]
class GetMergeRequestActionTest extends TestCase
{
    private const PROJECT_ID = 59858;

    private static function makeMergeRequest(): \stdClass
    {
        return (object) [
            'iid' => 219,
            'title' => 'Issue #3625000: Fix the widget',
            'author' => (object) ['username' => 'contributor'],
            'state' => 'opened',
            'draft' => true,
            'source_branch' => '3625000-fix-widget',
            'target_branch' => '1.x',
            'sha' => 'abc123',
            'web_url' => 'https://git.drupalcode.org/project/tool/-/merge_requests/219',
            'detailed_merge_status' => 'draft_status',
            'has_conflicts' => false,
            'blocking_discussions_resolved' => false,
            'head_pipeline' => (object) [
                'status' => 'success',
                'web_url' => 'https://git.drupalcode.org/project/tool/-/pipelines/99',
            ],
        ];
    }

    /**
     * @return \stdClass[]
     */
    private static function makeNotes(): array
    {
        return [
            (object) ['id' => 1, 'body' => 'added 1 commit', 'system' => true, 'author' => (object) ['username' => 'contributor']],
            (object) ['id' => 2, 'body' => 'Pipeline passed.', 'system' => false, 'author' => (object) ['username' => 'drupalbot']],
            (object) [
                'id' => 3625786,
                'type' => 'DiffNote',
                'body' => 'This needs a null check.',
                'system' => false,
                'author' => (object) ['username' => 'reviewer'],
                'position' => (object) ['new_path' => 'src/Widget.php', 'new_line' => 42],
            ],
        ];
    }

    private function gitLabClient(bool $expectNotes): GitLabClient&MockObject
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')
            ->with('project/tool')
            ->willReturn((object) ['id' => self::PROJECT_ID]);
        $gitLabClient->method('getMergeRequest')
            ->with(self::PROJECT_ID, 219)
            ->willReturn(self::makeMergeRequest());
        $gitLabClient->method('getMergeRequestClosesIssues')
            ->with(self::PROJECT_ID, 219)
            ->willReturn([(object) [
                'iid' => 3625000,
                'title' => 'Fix the widget',
                'state' => 'opened',
                'labels' => ['state::needsReview'],
                'web_url' => 'https://git.drupalcode.org/project/tool/-/work_items/3625000',
            ]]);
        $gitLabClient->expects($expectNotes ? self::once() : self::never())
            ->method('getMergeRequestNotes')
            ->with(self::PROJECT_ID, 219)
            ->willReturn(self::makeNotes());
        return $gitLabClient;
    }

    private static function ref(string $input): MergeRequestRef
    {
        $ref = MergeRequestRef::tryParse($input);
        self::assertNotNull($ref);
        return $ref;
    }

    public function testMergeRequestStateAndClosingIssues(): void
    {
        $action = new GetMergeRequestAction($this->createMock(Client::class), $this->gitLabClient(false));
        $result = $action('', 219, self::ref('project/tool!219'));

        self::assertSame('Issue #3625000: Fix the widget', $result->title);
        self::assertSame('contributor', $result->author);
        self::assertTrue($result->draft);
        self::assertSame('abc123', $result->headSha);
        self::assertSame('draft_status', $result->detailedMergeStatus);
        self::assertFalse($result->blockingDiscussionsResolved);
        self::assertSame('success', $result->pipelineStatus);
        self::assertSame('https://git.drupalcode.org/project/tool/-/pipelines/99', $result->pipelineUrl);
        self::assertCount(1, $result->closesIssues);
        self::assertSame(3625000, $result->closesIssues[0]->iid);
        self::assertSame(['state::needsReview'], $result->closesIssues[0]->labels);
        self::assertSame([], $result->comments);
        self::assertNull($result->targetNoteId);
    }

    public function testMissingHeadPipelineReportsNoStatus(): void
    {
        $mergeRequest = self::makeMergeRequest();
        $mergeRequest->head_pipeline = null;
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn((object) ['id' => self::PROJECT_ID]);
        $gitLabClient->method('getMergeRequest')->willReturn($mergeRequest);
        $gitLabClient->method('getMergeRequestClosesIssues')->willReturn([]);

        $result = (new GetMergeRequestAction($this->createMock(Client::class), $gitLabClient))('', 219, self::ref('project/tool!219'));

        self::assertNull($result->pipelineStatus);
        self::assertNull($result->jsonSerialize()['head_pipeline']);
    }

    public function testWithCommentsDropsSystemAndBotNotes(): void
    {
        $action = new GetMergeRequestAction($this->createMock(Client::class), $this->gitLabClient(true));
        $result = $action('', 219, self::ref('project/tool!219'), withComments: true);

        self::assertSame([3625786], array_map(static fn(GitLabNote $note) => $note->id, $result->comments));
        self::assertSame('src/Widget.php', $result->comments[0]->path);
        self::assertSame(42, $result->comments[0]->line);
    }

    public function testIncludeBotCommentsKeepsBotNotes(): void
    {
        $action = new GetMergeRequestAction($this->createMock(Client::class), $this->gitLabClient(true));
        $result = $action('', 219, self::ref('project/tool!219'), withComments: true, includeBotComments: true);

        self::assertSame([2, 3625786], array_map(static fn(GitLabNote $note) => $note->id, $result->comments));
    }

    public function testNoteFragmentImpliesCommentsAndMarksTheNote(): void
    {
        $action = new GetMergeRequestAction($this->createMock(Client::class), $this->gitLabClient(true));
        $result = $action('', 219, self::ref('https://git.drupalcode.org/project/tool/-/merge_requests/219#note_3625786'));

        self::assertSame(3625786, $result->targetNoteId);
        self::assertTrue($result->isTargetNote($result->comments[0]));

        $json = $result->jsonSerialize();
        self::assertSame(3625786, $json['target_note_id']);
        self::assertSame(
            [
                'id' => 3625786,
                'body' => 'This needs a null check.',
                'author' => 'reviewer',
                'created_at' => '',
                'path' => 'src/Widget.php',
                'line' => 42,
                'is_target' => true,
            ],
            $json['comments'][0]
        );
    }

    public function testNoteFragmentKeepsALinkedBotNote(): void
    {
        $action = new GetMergeRequestAction($this->createMock(Client::class), $this->gitLabClient(true));
        $result = $action('', 219, self::ref('https://git.drupalcode.org/project/tool/-/merge_requests/219#note_2'));

        self::assertSame([2, 3625786], array_map(static fn(GitLabNote $note) => $note->id, $result->comments));
        self::assertTrue($result->isTargetNote($result->comments[0]));
        self::assertFalse($result->isTargetNote($result->comments[1]));
    }
}
