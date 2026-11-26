<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Action\GitLab;

use mglaman\DrupalOrg\Action\GitLab\GetIssueTemplateAction;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\Issue\IssueTemplateResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetIssueTemplateAction::class)]
#[CoversClass(IssueTemplateResult::class)]
class GetIssueTemplateActionTest extends TestCase
{
    private function client(bool $hasIssueQueue = false): Client
    {
        $client = $this->createMock(Client::class);
        $client->method('getProject')
            ->with('tool')
            ->willReturn(new Project('3346420', 'Tool API', 'tool', $hasIssueQueue));
        return $client;
    }

    public function testFetchesDefaultTemplate(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssueTemplateNames')->with('project/tool')->willReturn(['Bug', 'Default']);
        $gitLabClient->expects(self::once())
            ->method('getIssueTemplate')
            ->with('project/tool', 'Default')
            ->willReturn("## Problem/Motivation\n");

        $result = (new GetIssueTemplateAction($this->client(), $gitLabClient))('project/tool');

        self::assertSame('Default', $result->name);
        self::assertSame("## Problem/Motivation\n", $result->content);
        self::assertSame(
            ['project_path' => 'project/tool', 'name' => 'Default', 'content' => "## Problem/Motivation\n", 'available' => ['Bug', 'Default']],
            $result->jsonSerialize(),
        );
    }

    public function testFetchesNamedTemplate(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssueTemplateNames')->willReturn(['Bug', 'Default']);
        $gitLabClient->method('getIssueTemplate')->with('project/tool', 'Bug')->willReturn('Steps');

        $result = (new GetIssueTemplateAction($this->client(), $gitLabClient))('tool', 'Bug');

        self::assertSame('Bug', $result->name);
        self::assertSame('Steps', $result->content);
    }

    public function testReturnsEmptyResultWithoutDefaultTemplate(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssueTemplateNames')->willReturn(['Bug']);
        $gitLabClient->expects(self::never())->method('getIssueTemplate');

        $result = (new GetIssueTemplateAction($this->client(), $gitLabClient))('tool');

        self::assertNull($result->name);
        self::assertSame('', $result->content);
        self::assertSame(['Bug'], $result->available);
    }

    public function testRejectsUnknownTemplateName(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssueTemplateNames')->willReturn(['Default']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('project/tool has no issue template named "Bogus". Available: Default.');
        (new GetIssueTemplateAction($this->client(), $gitLabClient))('tool', 'Bogus');
    }

    public function testRejectsLegacyIssueQueueProject(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::never())->method('getIssueTemplateNames');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('https://www.drupal.org/node/add/project-issue/tool');
        (new GetIssueTemplateAction($this->client(true), $gitLabClient))('tool');
    }

    public function testWrapsGitLabFailure(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getIssueTemplateNames')->willThrowException(new \Exception('Server error', 500));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to list the issue templates of project/tool: Server error');
        (new GetIssueTemplateAction($this->client(), $gitLabClient))('tool');
    }
}
