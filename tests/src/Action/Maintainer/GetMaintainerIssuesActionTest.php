<?php

namespace mglaman\DrupalOrg\Tests\Action\Maintainer;

use mglaman\DrupalOrg\Action\Maintainer\GetMaintainerIssuesAction;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerIssuesResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetMaintainerIssuesAction::class)]
#[CoversClass(MaintainerIssuesResult::class)]
class GetMaintainerIssuesActionTest extends TestCase
{
    private static function makeMockFeed(string $title, array $items): object
    {
        $feed = new class ($title, $items) {
            public function __construct(
                private readonly string $title,
                private readonly array $items
            ) {
            }

            public function toArray(): array
            {
                return ['title' => $this->title, 'item' => $this->items];
            }
        };
        return $feed;
    }

    private static function makeItems(): array
    {
        return [
            [
                'title' => 'Fix the thing',
                'link' => 'https://www.drupal.org/project/mymodule/issues/1234567',
            ],
            [
                'title' => 'Another issue',
                'link' => 'https://www.drupal.org/project/othermodule/issues/7654321',
            ],
        ];
    }

    public function testInvokeAllType(): void
    {
        $capturedUrl = null;
        $feedLoader = function (string $url) use (&$capturedUrl): object {
            $capturedUrl = $url;
            return self::makeMockFeed('Issues for testuser', self::makeItems());
        };

        $action = new GetMaintainerIssuesAction($feedLoader);
        $result = $action('testuser', 'all');

        self::assertInstanceOf(MaintainerIssuesResult::class, $result);
        self::assertSame('Issues for testuser', $result->feedTitle);
        self::assertCount(2, $result->items);
        self::assertSame('mymodule', $result->items[0]['project']);
        self::assertSame('Fix the thing', $result->items[0]['title']);
        self::assertSame('https://www.drupal.org/project/mymodule/issues/1234567', $result->items[0]['link']);
        self::assertStringNotContainsString('status', $capturedUrl);
    }

    public function testInvokeRtbcType(): void
    {
        $capturedUrl = null;
        $feedLoader = function (string $url) use (&$capturedUrl): object {
            $capturedUrl = $url;
            return self::makeMockFeed('RTBC issues for testuser', self::makeItems());
        };

        $action = new GetMaintainerIssuesAction($feedLoader);
        $result = $action('testuser', 'rtbc');

        self::assertInstanceOf(MaintainerIssuesResult::class, $result);
        self::assertStringContainsString('status[0]=14', $capturedUrl);
    }

    public function testJsonSerialize(): void
    {
        $feedLoader = static fn(string $u): object => self::makeMockFeed('Test feed', self::makeItems());

        $action = new GetMaintainerIssuesAction($feedLoader);
        $result = $action('testuser', 'all');

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('Test feed', $decoded['feed_title']);
        self::assertCount(2, $decoded['items']);
        self::assertSame('mymodule', $decoded['items'][0]['project']);
        self::assertSame('Fix the thing', $decoded['items'][0]['title']);
    }
}
