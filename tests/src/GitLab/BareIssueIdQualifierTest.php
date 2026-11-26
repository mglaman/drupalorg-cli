<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\GitLab;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\GitLab\BareIssueIdQualifier;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrg\ProjectRemote;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BareIssueIdQualifier::class)]
class BareIssueIdQualifierTest extends TestCase
{
    private function client(?Project $project): Client
    {
        $client = $this->createMock(Client::class);
        $client->method('getProject')->with('jsonapi_search_api')->willReturn($project);
        return $client;
    }

    private static function workItemProject(): Project
    {
        return new Project('3300000', 'JSON:API Search API', 'jsonapi_search_api', hasIssueQueue: false);
    }

    public function testScopesBareIdToWorkItemCheckout(): void
    {
        $qualifier = new BareIssueIdQualifier($this->client(self::workItemProject()));

        $qualified = $qualifier->qualify('3612675', new ProjectRemote('jsonapi_search_api'));

        self::assertSame('jsonapi_search_api#3612675', $qualified);
        self::assertSame('project/jsonapi_search_api', WorkItemRef::tryParse($qualified)?->projectPath);
    }

    public function testLeavesBareIdInIssueQueueCheckout(): void
    {
        $project = new Project('3300000', 'JSON:API Search API', 'jsonapi_search_api', hasIssueQueue: true);
        $qualifier = new BareIssueIdQualifier($this->client($project));

        self::assertSame('3612675', $qualifier->qualify('3612675', new ProjectRemote('jsonapi_search_api')));
    }

    public function testLeavesBareIdWhenCheckoutProjectIsUnknown(): void
    {
        $qualifier = new BareIssueIdQualifier($this->client(null));

        self::assertSame('3612675', $qualifier->qualify('3612675', new ProjectRemote('jsonapi_search_api')));
    }

    public function testLeavesBareIdOutsideDrupalCheckout(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('getProject');

        self::assertSame('3612675', (new BareIssueIdQualifier($client))->qualify('3612675', null));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function qualifiedInputProvider(): array
    {
        return [
            'shorthand' => ['tool#3612675'],
            'explicit path' => ['project/tool#3612675'],
            'url' => ['https://git.drupalcode.org/project/tool/-/work_items/3612675'],
            'empty' => [''],
        ];
    }

    #[DataProvider('qualifiedInputProvider')]
    public function testLeavesOtherInputUnchanged(string $input): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('getProject');

        $qualified = (new BareIssueIdQualifier($client))->qualify($input, new ProjectRemote('jsonapi_search_api'));

        self::assertSame($input, $qualified);
    }
}
