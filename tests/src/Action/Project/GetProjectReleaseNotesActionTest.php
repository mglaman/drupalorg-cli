<?php

namespace mglaman\DrupalOrg\Tests\Action\Project;

use mglaman\DrupalOrg\Action\Project\GetProjectReleaseNotesAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\Result\Project\ProjectReleaseNotesResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetProjectReleaseNotesAction::class)]
#[CoversClass(ProjectReleaseNotesResult::class)]
class GetProjectReleaseNotesActionTest extends TestCase
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

    private static function releaseFixture(): \stdClass
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../../fixtures/release_node.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private static function makeRawRelease(\stdClass $releaseNode): \stdClass
    {
        return (object) ['list' => [$releaseNode]];
    }

    private static function makeEmptyList(): \stdClass
    {
        return (object) ['list' => []];
    }

    public function testInvoke(): void
    {
        $project = Project::fromStdClass(self::projectFixture());

        $client = $this->createMock(Client::class);
        $client->method('getProject')->with('address')->willReturn($project);
        $client->method('requestRaw')->willReturn(self::makeRawRelease(self::releaseFixture()));

        $action = new GetProjectReleaseNotesAction($client);
        $result = $action('address', '10.6.3');

        self::assertInstanceOf(ProjectReleaseNotesResult::class, $result);
        self::assertSame('address', $result->projectName);
        self::assertSame('10.6.3', $result->version);
        self::assertSame('<p>Release notes content.</p>', $result->body);
    }

    public function testInvokeWithSemverFallback(): void
    {
        $project = Project::fromStdClass(self::projectFixture());
        $releaseNode = self::releaseFixture();
        $releaseNode->field_release_version = '8.x-1.0';

        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn($project);
        $client->method('requestRaw')->willReturnOnConsecutiveCalls(
            self::makeEmptyList(),
            self::makeRawRelease($releaseNode)
        );

        $action = new GetProjectReleaseNotesAction($client);
        $result = $action('address', '1.0.0');

        self::assertSame('8.x-1.0', $result->version);
        self::assertSame('<p>Release notes content.</p>', $result->body);
    }

    public function testInvokeThrowsWhenNoRelease(): void
    {
        $project = Project::fromStdClass(self::projectFixture());

        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn($project);
        $client->method('requestRaw')->willReturn(self::makeEmptyList());

        $action = new GetProjectReleaseNotesAction($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No release found for 99.9.9.');
        $action('address', '99.9.9');
    }

    public function testInvokeThrowsWhenSemverFallbackAlsoFails(): void
    {
        $project = Project::fromStdClass(self::projectFixture());

        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn($project);
        $client->method('requestRaw')->willReturn(self::makeEmptyList());

        $action = new GetProjectReleaseNotesAction($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No release found for 8.x-1.0.');
        $action('address', '1.0.0');
    }

    public function testInvokeThrowsWhenProjectNotFound(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn(null);

        $action = new GetProjectReleaseNotesAction($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Project address not found.');
        $action('address', '1.0.0');
    }

    public function testJsonSerialize(): void
    {
        $project = Project::fromStdClass(self::projectFixture());

        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn($project);
        $client->method('requestRaw')->willReturn(self::makeRawRelease(self::releaseFixture()));

        $action = new GetProjectReleaseNotesAction($client);
        $result = $action('address', '10.6.3');

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('address', $decoded['project_name']);
        self::assertSame('10.6.3', $decoded['version']);
        self::assertSame('<p>Release notes content.</p>', $decoded['body']);
    }
}
