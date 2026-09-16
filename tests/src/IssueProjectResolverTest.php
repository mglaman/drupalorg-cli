<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrg\IssueProjectResolver;
use mglaman\DrupalOrg\MigratedIssueException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IssueProjectResolver::class)]
class IssueProjectResolverTest extends TestCase
{
    private static function makeIssueNode(string $nid, string $project): IssueNode
    {
        return new IssueNode(
            nid: $nid,
            title: 'sdx on shared hosting',
            created: 1693195104,
            changed: 1727653295,
            commentCount: 0,
            fieldIssueVersion: '1.0.x-dev',
            fieldIssueStatus: 1,
            fieldIssueCategory: 1,
            fieldIssuePriority: 200,
            fieldIssueComponent: 'Code',
            fieldProjectId: '1',
            fieldProjectMachineName: $project,
            bodyValue: null,
            authorId: null,
            fieldIssueFiles: [],
            comments: [],
        );
    }

    private static function migrated(string $nid, string $project): MigratedIssueException
    {
        return new MigratedIssueException(
            $nid,
            new WorkItemRef('project/' . $project, (int) $nid),
            sprintf('https://git.drupalcode.org/project/%s/-/work_items/%s', $project, $nid)
        );
    }

    public function testExplicitProjectSkipsNodeLookup(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('getNode');

        $resolver = new IssueProjectResolver($client);

        self::assertSame('campaign', $resolver->resolve('3615648', 'campaign'));
        self::assertSame('campaign', $resolver->resolve('3615648', 'campaign', 'sdx'));
    }

    public function testRepositoryProjectWinsWhenNodeIsMissing(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')
            ->willThrowException(new \RuntimeException('Node 3615635 was not found on Drupal.org.'));

        $resolver = new IssueProjectResolver($client);

        self::assertSame('campaign', $resolver->resolve('3615635', null, 'campaign'));
    }

    public function testRepositoryProjectConfirmedByNode(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode('3383637', 'campaign'));

        $resolver = new IssueProjectResolver($client);

        self::assertSame('campaign', $resolver->resolve('3383637', null, 'campaign'));
    }

    public function testRepositoryProjectWinsWhenNodeHasNoProject(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode('3383637', ''));

        $resolver = new IssueProjectResolver($client);

        self::assertSame('campaign', $resolver->resolve('3383637', null, 'campaign'));
    }

    public function testCollisionBetweenNodeAndRepositoryFails(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode('3615648', 'sdx'));

        $resolver = new IssueProjectResolver($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Issue 3615648 belongs to project "sdx" on Drupal.org, but this repository is project "campaign". '
            . 'Pass campaign#3615648 for the GitLab work item or sdx#3615648 for the Drupal.org issue.'
        );
        $resolver->resolve('3615648', null, 'campaign');
    }

    public function testBareNidUsesNodeProject(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->with('3383637')->willReturn(self::makeIssueNode('3383637', 'drupal'));

        $resolver = new IssueProjectResolver($client);

        self::assertSame('drupal', $resolver->resolve('3383637'));
    }

    public function testBareNidForMigratedIssueUsesRedirectProject(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willThrowException(self::migrated('3617735', 'restrict_route_by_ip'));

        $resolver = new IssueProjectResolver($client);

        self::assertSame('restrict_route_by_ip', $resolver->resolve('3617735'));
    }

    public function testMigratedIssueConfirmedByRepository(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willThrowException(self::migrated('3617735', 'restrict_route_by_ip'));

        $resolver = new IssueProjectResolver($client);

        self::assertSame('restrict_route_by_ip', $resolver->resolve('3617735', null, 'restrict_route_by_ip'));
    }

    public function testMigratedIssueInAnotherRepositoryFails(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willThrowException(self::migrated('3617735', 'restrict_route_by_ip'));

        $resolver = new IssueProjectResolver($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Issue 3617735 belongs to project "restrict_route_by_ip" on Drupal.org');
        $resolver->resolve('3617735', null, 'campaign');
    }

    public function testBareNidWithoutProjectFails(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')->willReturn(self::makeIssueNode('3591806', ''));

        $resolver = new IssueProjectResolver($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Could not resolve a project for issue 3591806. Pass the project explicitly as project#3591806.'
        );
        $resolver->resolve('3591806');
    }

    public function testBareNidForMissingNodeFailsWithHint(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getNode')
            ->willThrowException(new \RuntimeException('Node 3615635 was not found on Drupal.org.'));

        $resolver = new IssueProjectResolver($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Node 3615635 was not found on Drupal.org. Pass the project explicitly as project#3615635.'
        );
        $resolver->resolve('3615635');
    }
}
