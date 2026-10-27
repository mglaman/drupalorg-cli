<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Action\GitLab;

use mglaman\DrupalOrg\Action\GitLab\PostWorkItemCommentAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\Issue\WorkItemCommentResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostWorkItemCommentAction::class)]
#[CoversClass(WorkItemCommentResult::class)]
class PostWorkItemCommentActionTest extends TestCase
{
    public function testPostsBodyAndReturnsNoteUrl(): void
    {
        $note = new \stdClass();
        $note->id = 3625786;

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('postIssueNote')
            ->with('project/tool', 3583107, "Thanks for retesting.\n\n`code` and \"quotes\" survive.")
            ->willReturn($note);

        $action = new PostWorkItemCommentAction($this->createMock(Client::class), $gitLabClient);
        $result = $action('tool#3583107', "Thanks for retesting.\n\n`code` and \"quotes\" survive.");

        self::assertSame('project/tool', $result->projectPath);
        self::assertSame(3583107, $result->issueIid);
        self::assertSame(3625786, $result->noteId);
        self::assertSame(
            'https://git.drupalcode.org/project/tool/-/work_items/3583107#note_3625786',
            $result->noteUrl(),
        );
    }

    public function testRejectsEmptyBodyWithoutPosting(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::never())->method('postIssueNote');

        $action = new PostWorkItemCommentAction($this->createMock(Client::class), $gitLabClient);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The comment body is empty.');
        $action('tool#3583107', "  \n\t");
    }

    public function testWrapsGitLabFailure(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('postIssueNote')->willThrowException(new \Exception('Forbidden', 403));

        $action = new PostWorkItemCommentAction($this->createMock(Client::class), $gitLabClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to post a comment to project/tool#3583107: Forbidden');
        $action('tool#3583107', 'Hello');
    }

    public function testThrowsWhenGitLabResponseLacksId(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('postIssueNote')->willReturn(new \stdClass());

        $action = new PostWorkItemCommentAction($this->createMock(Client::class), $gitLabClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/did not contain an id/');
        $action('tool#3583107', 'Hello');
    }
}
