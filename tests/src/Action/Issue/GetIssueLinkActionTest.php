<?php

namespace mglaman\DrupalOrg\Tests\Action\Issue;

use mglaman\DrupalOrg\Action\Issue\GetIssueLinkAction;
use mglaman\DrupalOrg\Result\Issue\IssueLinkResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetIssueLinkAction::class)]
#[CoversClass(IssueLinkResult::class)]
class GetIssueLinkActionTest extends TestCase
{
    public function testInvoke(): void
    {
        $action = new GetIssueLinkAction();
        $result = $action('3383637');

        self::assertInstanceOf(IssueLinkResult::class, $result);
        self::assertSame('https://www.drupal.org/node/3383637', $result->url);
    }

    public function testJsonSerialize(): void
    {
        $action = new GetIssueLinkAction();
        $result = $action('3383637');

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('https://www.drupal.org/node/3383637', $decoded['url']);
    }
}
