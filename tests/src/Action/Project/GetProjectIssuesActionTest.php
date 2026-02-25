<?php

namespace mglaman\DrupalOrg\Tests\Action\Project;

use mglaman\DrupalOrg\Action\Project\GetProjectIssuesAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetProjectIssuesAction::class)]
#[CoversClass(ProjectIssuesResult::class)]
class GetProjectIssuesActionTest extends TestCase
{
    private static function projectFixture(): \stdClass
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../../fixtures/project_node.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private static function makeRawReleases(): \stdClass
    {
        return (object) [
            'list' => [
                (object) ['field_release_version' => '8.x-1.5', 'nid' => '100'],
                (object) ['field_release_version' => '8.x-1.4', 'nid' => '99'],
            ],
        ];
    }

    private static function makeRawIssues(): \stdClass
    {
        return (object) [
            'list' => [
                (object) ['nid' => '200', 'field_issue_status' => '1', 'title' => 'Test issue'],
            ],
        ];
    }

    public function testInvoke(): void
    {
        $project = Project::fromStdClass(self::projectFixture());

        $client = $this->createMock(Client::class);
        $client->method('getProject')->with('address')->willReturn($project);
        $client->method('requestRaw')->willReturnOnConsecutiveCalls(
            self::makeRawReleases(),
            self::makeRawIssues()
        );

        $action = new GetProjectIssuesAction($client);
        $result = $action('address', 'all', '8.x', 10);

        self::assertInstanceOf(ProjectIssuesResult::class, $result);
        self::assertSame('Address', $result->projectTitle);
        self::assertCount(1, $result->issues);
        self::assertSame('200', $result->issues[0]->nid);
        self::assertSame('Test issue', $result->issues[0]->title);
    }

    public function testInvokeThrowsWhenProjectNotFound(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn(null);

        $action = new GetProjectIssuesAction($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Project address not found.');
        $action('address', 'all', '8.x', 10);
    }

    public function testJsonSerialize(): void
    {
        $project = Project::fromStdClass(self::projectFixture());

        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn($project);
        $client->method('requestRaw')->willReturnOnConsecutiveCalls(
            self::makeRawReleases(),
            self::makeRawIssues()
        );

        $action = new GetProjectIssuesAction($client);
        $result = $action('address', 'all', '8.x', 10);

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('Address', $decoded['project_title']);
        self::assertCount(1, $decoded['issues']);
    }
}
