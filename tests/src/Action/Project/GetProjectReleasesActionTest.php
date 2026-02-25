<?php

namespace mglaman\DrupalOrg\Tests\Action\Project;

use mglaman\DrupalOrg\Action\Project\GetProjectReleasesAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\Entity\Release;
use mglaman\DrupalOrg\Result\Project\ProjectReleasesResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetProjectReleasesAction::class)]
#[CoversClass(ProjectReleasesResult::class)]
class GetProjectReleasesActionTest extends TestCase
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

    public function testInvoke(): void
    {
        $project = Project::fromStdClass(self::projectFixture());
        $release = Release::fromStdClass(self::releaseFixture());

        $client = $this->createMock(Client::class);
        $client->method('getProject')->with('address')->willReturn($project);
        $client->method('getProjectReleases')->with('2421989', ['field_release_update_status' => 0])->willReturn([$release]);

        $action = new GetProjectReleasesAction($client);
        $result = $action('address');

        self::assertInstanceOf(ProjectReleasesResult::class, $result);
        self::assertSame('Address', $result->projectTitle);
        self::assertSame('address', $result->projectName);
        self::assertCount(1, $result->releases);
        self::assertSame('10.6.3', $result->releases[0]->fieldReleaseVersion);
    }

    public function testInvokeThrowsWhenProjectNotFound(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn(null);

        $action = new GetProjectReleasesAction($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Project address not found.');
        $action('address');
    }

    public function testJsonSerialize(): void
    {
        $project = Project::fromStdClass(self::projectFixture());
        $release = Release::fromStdClass(self::releaseFixture());

        $client = $this->createMock(Client::class);
        $client->method('getProject')->willReturn($project);
        $client->method('getProjectReleases')->willReturn([$release]);

        $action = new GetProjectReleasesAction($client);
        $result = $action('address');

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('Address', $decoded['project_title']);
        self::assertSame('address', $decoded['project_name']);
        self::assertSame('10.6.3', $decoded['releases'][0]['field_release_version']);
    }
}
