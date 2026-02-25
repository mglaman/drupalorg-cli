<?php

namespace mglaman\DrupalOrg\Tests\Action\Project;

use mglaman\DrupalOrg\Action\Project\GetProjectKanbanLinkAction;
use mglaman\DrupalOrg\Result\Project\ProjectLinkResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetProjectKanbanLinkAction::class)]
#[CoversClass(ProjectLinkResult::class)]
class GetProjectKanbanLinkActionTest extends TestCase
{
    public function testInvoke(): void
    {
        $action = new GetProjectKanbanLinkAction();
        $result = $action('address');

        self::assertInstanceOf(ProjectLinkResult::class, $result);
        self::assertSame('https://contribkanban.com/board/address', $result->url);
    }

    public function testJsonSerialize(): void
    {
        $action = new GetProjectKanbanLinkAction();
        $result = $action('address');

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('https://contribkanban.com/board/address', $decoded['url']);
    }
}
