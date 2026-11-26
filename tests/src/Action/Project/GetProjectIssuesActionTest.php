<?php

namespace mglaman\DrupalOrg\Tests\Action\Project;

use mglaman\DrupalOrg\Action\Project\GetProjectIssuesAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\Enum\ProjectIssueCategory;
use mglaman\DrupalOrg\Enum\ProjectIssueType;
use mglaman\DrupalOrg\Result\Project\ProjectIssuesResult;
use mglaman\DrupalOrg\Request;
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

    private static function makeRawIssues(): \stdClass
    {
        return (object) [
            'list' => [
                (object) ['nid' => '200', 'field_issue_status' => '1', 'title' => 'Test issue'],
            ],
        ];
    }

    public function testInvokeWithCategory(): void
    {
        $project = Project::fromStdClass(self::projectFixture());

        $client = $this->createMock(Client::class);
        $capturedParams = [];
        $client->method('requestRaw')->willReturnCallback(
            function (Request $request) use (&$capturedParams) {
                $capturedParams[] = $request->getOptions();
                return (object) ['list' => []];
            }
        );

        $action = new GetProjectIssuesAction($client);
        $action($project, ProjectIssueType::All, 10, ProjectIssueCategory::Bug);

        // The issues request should include field_issue_category = 1 (Bug)
        self::assertArrayHasKey('field_issue_category', $capturedParams[0]);
        self::assertSame(1, $capturedParams[0]['field_issue_category']);
    }

    public function testInvokeWithoutCategoryDoesNotAddParam(): void
    {
        $project = Project::fromStdClass(self::projectFixture());

        $client = $this->createMock(Client::class);
        $capturedParams = [];
        $client->method('requestRaw')->willReturnCallback(
            function (Request $request) use (&$capturedParams) {
                $capturedParams[] = $request->getOptions();
                return (object) ['list' => []];
            }
        );

        $action = new GetProjectIssuesAction($client);
        $action($project, ProjectIssueType::All, 10);

        self::assertArrayNotHasKey('field_issue_category', $capturedParams[0]);
    }

    public function testInvokeSendsOneRequestWithoutVersionFilter(): void
    {
        $project = Project::fromStdClass(self::projectFixture());

        $client = $this->createMock(Client::class);
        $capturedParams = [];
        $client->method('requestRaw')->willReturnCallback(
            function (Request $request) use (&$capturedParams) {
                $capturedParams[] = $request->getOptions();
                return (object) ['list' => []];
            }
        );

        $action = new GetProjectIssuesAction($client);
        $action($project, ProjectIssueType::All, 10);

        self::assertCount(1, $capturedParams);
        self::assertSame('project_issue', $capturedParams[0]['type']);
        self::assertArrayNotHasKey('field_issue_version', $capturedParams[0]);
    }

    public function testInvoke(): void
    {
        $project = Project::fromStdClass(self::projectFixture());

        $client = $this->createMock(Client::class);
        $client->method('requestRaw')->willReturn(self::makeRawIssues());

        $action = new GetProjectIssuesAction($client);
        $result = $action($project, ProjectIssueType::All, 10);

        self::assertInstanceOf(ProjectIssuesResult::class, $result);
        self::assertSame('Address', $result->projectTitle);
        self::assertCount(1, $result->issues);
        self::assertInstanceOf(IssueNode::class, $result->issues[0]);
        self::assertSame('200', $result->issues[0]->nid);
        self::assertSame('Test issue', $result->issues[0]->title);
    }

    public function testJsonSerialize(): void
    {
        $project = Project::fromStdClass(self::projectFixture());

        $client = $this->createMock(Client::class);
        $client->method('requestRaw')->willReturn(self::makeRawIssues());

        $action = new GetProjectIssuesAction($client);
        $result = $action($project, ProjectIssueType::All, 10);

        $json = json_encode($result);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertSame('Address', $decoded['project_title']);
        self::assertCount(1, $decoded['issues']);
        self::assertSame('200', $decoded['issues'][0]['nid']);
        self::assertSame(1, $decoded['issues'][0]['field_issue_status']);
    }
}
