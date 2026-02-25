<?php

namespace mglaman\DrupalOrg\Tests\Action\Project;

use mglaman\DrupalOrg\Action\Project\GetProjectLinkAction;
use mglaman\DrupalOrg\Result\Project\ProjectLinkResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetProjectLinkAction::class)]
#[CoversClass(ProjectLinkResult::class)]
class GetProjectLinkActionTest extends TestCase
{
    public function testInvoke(): void
    {
        $action = new GetProjectLinkAction();
        $result = $action('address');

        self::assertInstanceOf(ProjectLinkResult::class, $result);
        self::assertSame('https://www.drupal.org/project/address', $result->url);
    }

    public function testJsonSerialize(): void
    {
        $action = new GetProjectLinkAction();
        $result = $action('address');

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('https://www.drupal.org/project/address', $decoded['url']);
    }
}
