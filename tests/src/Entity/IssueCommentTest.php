<?php

namespace mglaman\DrupalOrg\Tests\Entity;

use mglaman\DrupalOrg\Entity\IssueComment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IssueComment::class)]
class IssueCommentTest extends TestCase
{
    private static function fixture(): \stdClass
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/comment_node.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function testFromStdClass(): void
    {
        $comment = IssueComment::fromStdClass(self::fixture());

        self::assertSame('15671234', $comment->cid);
        self::assertSame('<p>Comment body.</p>', $comment->bodyValue);
        self::assertSame(1700000000, $comment->created);
        self::assertSame('99999', $comment->authorId);
        self::assertSame('testuser', $comment->authorName);
    }

    public function testJsonSerialize(): void
    {
        $comment = IssueComment::fromStdClass(self::fixture());

        $json = json_encode($comment);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('15671234', $decoded['cid']);
        self::assertArrayNotHasKey('subject', $decoded);
        self::assertSame('<p>Comment body.</p>', $decoded['body_value']);
        self::assertSame(1700000000, $decoded['created']);
        self::assertSame('99999', $decoded['author_id']);
        self::assertSame('testuser', $decoded['author_name']);
    }

    public function testFromStdClassWithMissingBody(): void
    {
        $data = new \stdClass();
        $data->cid = '1';
        $data->created = '1700000000';
        $data->name = 'user1';
        $data->author = new \stdClass();
        $data->author->id = '12345';

        $comment = IssueComment::fromStdClass($data);

        self::assertNull($comment->bodyValue);
    }
}
