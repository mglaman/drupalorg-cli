<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Action\GitLab;

use mglaman\DrupalOrg\Action\GitLab\PostWorkItemSlashCommandAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\Issue\SlashCommandResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostWorkItemSlashCommandAction::class)]
#[CoversClass(SlashCommandResult::class)]
class PostWorkItemSlashCommandActionTest extends TestCase
{
    private static function makeIssueNode(): IssueNode
    {
        return new IssueNode(
            nid: '3586157',
            title: 'Example AI context issue',
            created: 1693195104,
            changed: 1727653295,
            commentCount: 0,
            fieldIssueVersion: '1.0.x-dev',
            fieldIssueStatus: 1,
            fieldIssueCategory: 1,
            fieldIssuePriority: 200,
            fieldIssueComponent: 'Code',
            fieldProjectId: '3060',
            fieldProjectMachineName: 'ai_context',
            bodyValue: null,
            authorId: null,
            fieldIssueFiles: [],
            comments: [],
        );
    }

    public function testPostsCommandAndReturnsResult(): void
    {
        $note = new \stdClass();
        $note->id = 4242;

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('postIssueNote')
            ->with('project/ai_context', 3586157, '/do:fork')
            ->willReturn($note);

        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('getNode');

        $action = new PostWorkItemSlashCommandAction($client, $gitLabClient);
        $result = $action('ai_context#3586157', '/do:fork');

        self::assertSame('project/ai_context', $result->projectPath);
        self::assertSame(3586157, $result->issueIid);
        self::assertSame('/do:fork', $result->command);
        self::assertSame(4242, $result->noteId);
        self::assertSame(
            'https://git.drupalcode.org/project/ai_context/-/work_items/3586157',
            $result->workItemUrl(),
        );
    }

    public function testResolvesBareNidViaDrupalOrg(): void
    {
        $note = new \stdClass();
        $note->id = 7;

        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('getNode')
            ->with('3586157')
            ->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('postIssueNote')
            ->with('project/ai_context', 3586157, '/do:assign me')
            ->willReturn($note);

        $action = new PostWorkItemSlashCommandAction($client, $gitLabClient);
        $result = $action('3586157', '/do:assign me');

        self::assertSame('project/ai_context', $result->projectPath);
        self::assertSame(3586157, $result->issueIid);
    }

    public function testAcceptsFullWorkItemUrl(): void
    {
        $note = new \stdClass();
        $note->id = 99;

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('postIssueNote')
            ->with('project/ai_context', 3586157, '/do:label ~state::rtbc')
            ->willReturn($note);

        $client = $this->createMock(Client::class);

        $action = new PostWorkItemSlashCommandAction($client, $gitLabClient);
        $result = $action(
            'https://git.drupalcode.org/project/ai_context/-/work_items/3586157',
            '/do:label ~state::rtbc',
        );

        self::assertSame(99, $result->noteId);
    }

    public function testRejectsUnparseableRef(): void
    {
        $client = $this->createMock(Client::class);
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::never())->method('postIssueNote');

        $action = new PostWorkItemSlashCommandAction($client, $gitLabClient);

        $this->expectException(\InvalidArgumentException::class);
        $action('not-a-ref', '/do:fork');
    }

    public function testWrapsGitLabFailureWithHelpfulError(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('postIssueNote')->willThrowException(new \Exception('Not Found', 404));

        $client = $this->createMock(Client::class);

        $action = new PostWorkItemSlashCommandAction($client, $gitLabClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Drupal\.org issue queue/');
        $action('ai_context#3586157', '/do:fork');
    }

    public function testTrimsBareNidWithSurroundingWhitespace(): void
    {
        $note = new \stdClass();
        $note->id = 11;

        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('getNode')
            ->with('3586157')
            ->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('postIssueNote')
            ->with('project/ai_context', 3586157, '/do:fork')
            ->willReturn($note);

        $action = new PostWorkItemSlashCommandAction($client, $gitLabClient);
        $result = $action("  3586157\n", '/do:fork');

        self::assertSame(11, $result->noteId);
    }

    public function testThrowsWhenGitLabResponseLacksId(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('postIssueNote')->willReturn(new \stdClass());

        $client = $this->createMock(Client::class);

        $action = new PostWorkItemSlashCommandAction($client, $gitLabClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/did not contain an id/');
        $action('ai_context#3586157', '/do:fork');
    }
}
