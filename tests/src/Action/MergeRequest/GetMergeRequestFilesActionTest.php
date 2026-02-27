<?php

namespace mglaman\DrupalOrg\Tests\Action\MergeRequest;

use mglaman\DrupalOrg\Action\MergeRequest\GetMergeRequestFilesAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestFilesResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetMergeRequestFilesAction::class)]
#[CoversClass(MergeRequestFilesResult::class)]
class GetMergeRequestFilesActionTest extends TestCase
{
    private static function makeIssueNode(): IssueNode
    {
        return new IssueNode(
            nid: '3383637',
            title: 'Test issue',
            created: 1693195104,
            changed: 1727653295,
            commentCount: 0,
            fieldIssueVersion: '11.x-dev',
            fieldIssueStatus: 1,
            fieldIssueCategory: 1,
            fieldIssuePriority: 200,
            fieldIssueComponent: 'Base system',
            fieldProjectId: '3060',
            fieldProjectMachineName: 'drupal',
            bodyValue: null,
            authorId: null,
            fieldIssueFiles: [],
            comments: [],
        );
    }

    private static function makeProject(): \stdClass
    {
        $project = new \stdClass();
        $project->id = 12345;
        return $project;
    }

    private static function makeFileDiff(
        string $path,
        bool $newFile = false,
        bool $deletedFile = false,
        bool $renamedFile = false,
    ): \stdClass {
        $d = new \stdClass();
        $d->new_path = $path;
        $d->new_file = $newFile;
        $d->deleted_file = $deletedFile;
        $d->renamed_file = $renamedFile;
        $d->diff = '';
        return $d;
    }

    public function testFileExtraction(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->with('3383637')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->with('issue/drupal-3383637')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequestDiffs')->with(12345, 7)->willReturn([
            self::makeFileDiff('src/Foo.php'),
            self::makeFileDiff('src/Bar.php', newFile: true),
            self::makeFileDiff('src/Old.php', deletedFile: true),
            self::makeFileDiff('src/Renamed.php', renamedFile: true),
        ]);

        $action = new GetMergeRequestFilesAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertInstanceOf(MergeRequestFilesResult::class, $result);
        self::assertSame(7, $result->iid);
        self::assertCount(4, $result->files);

        self::assertSame('src/Foo.php', $result->files[0]['path']);
        self::assertFalse($result->files[0]['new_file']);
        self::assertFalse($result->files[0]['deleted_file']);
        self::assertFalse($result->files[0]['renamed_file']);

        self::assertSame('src/Bar.php', $result->files[1]['path']);
        self::assertTrue($result->files[1]['new_file']);

        self::assertSame('src/Old.php', $result->files[2]['path']);
        self::assertTrue($result->files[2]['deleted_file']);

        self::assertSame('src/Renamed.php', $result->files[3]['path']);
        self::assertTrue($result->files[3]['renamed_file']);
    }

    public function testEmptyDiffs(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode());

        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getProject')->willReturn(self::makeProject());
        $gitLabClient->method('getMergeRequestDiffs')->willReturn([]);

        $action = new GetMergeRequestFilesAction($client, $gitLabClient);
        $result = $action('3383637', 7);

        self::assertSame([], $result->files);
    }
}
