<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Tests\Action\Project;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use mglaman\DrupalOrg\Action\Project\GetProjectContainsAction;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\Project\ProjectContainsResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetProjectContainsAction::class)]
#[CoversClass(ProjectContainsResult::class)]
class GetProjectContainsActionTest extends TestCase
{
    private const FULL_SHA = 'd6ef1a7c0ffee0000000000000000000000000ab';

    private static function commit(): \stdClass
    {
        return (object) [
            'id' => self::FULL_SHA,
            'short_id' => 'd6ef1a7c',
            'title' => 'Fix tool discovery cache',
            'committed_date' => '2025-03-14T09:26:53.000+00:00',
            'web_url' => 'https://git.drupalcode.org/project/tool/-/commit/' . self::FULL_SHA,
        ];
    }

    /**
     * @param string[] $branches
     * @param string[] $tags
     * @return \stdClass[]
     */
    private static function refs(array $branches, array $tags): array
    {
        return [
            ...array_map(static fn(string $name) => (object) ['type' => 'branch', 'name' => $name], $branches),
            ...array_map(static fn(string $name) => (object) ['type' => 'tag', 'name' => $name], $tags),
        ];
    }

    /**
     * @param string[] $branches
     * @param string[] $tags
     */
    private function gitLabClientWithRefs(array $branches, array $tags): GitLabClient
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->expects(self::once())
            ->method('getCommit')
            ->with('project/tool', 'd6ef1a7')
            ->willReturn(self::commit());
        $gitLabClient->expects(self::once())
            ->method('getCommitRefs')
            ->with('project/tool', self::FULL_SHA)
            ->willReturn(self::refs($branches, $tags));
        return $gitLabClient;
    }

    public function testTaggedCommit(): void
    {
        $action = new GetProjectContainsAction($this->gitLabClientWithRefs(['1.x'], ['1.0.0-beta12']));
        $result = $action('tool', 'd6ef1a7');

        self::assertSame('tool', $result->projectName);
        self::assertSame(self::FULL_SHA, $result->sha);
        self::assertSame('Fix tool discovery cache', $result->title);
        self::assertSame('2025-03-14T09:26:53.000+00:00', $result->committedDate);
        self::assertSame('https://git.drupalcode.org/project/tool/-/commit/' . self::FULL_SHA, $result->webUrl);
        self::assertSame(['1.x'], $result->branches);
        self::assertSame(['1.0.0-beta12'], $result->tags);
    }

    public function testCommitOnlyOnABranch(): void
    {
        $action = new GetProjectContainsAction($this->gitLabClientWithRefs(['2.x', '1.x'], []));
        $result = $action('tool', 'd6ef1a7');

        self::assertSame(['1.x', '2.x'], $result->branches);
        self::assertSame([], $result->tags);
        self::assertSame([], $result->jsonSerialize()['tags']);
    }

    public function testUnknownShaThrowsNamingProjectAndSha(): void
    {
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getCommit')->willThrowException(new ClientException(
            '404 Commit Not Found',
            new Request('GET', 'projects/project%2Ftool/repository/commits/deadbee'),
            new Response(404),
        ));
        $gitLabClient->expects(self::never())->method('getCommitRefs');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Commit deadbee not found in project tool.');
        (new GetProjectContainsAction($gitLabClient))('tool', 'deadbee');
    }

    public function testOtherClientErrorsPropagate(): void
    {
        $forbidden = new ClientException(
            '403 Forbidden',
            new Request('GET', 'projects/project%2Ftool/repository/commits/d6ef1a7'),
            new Response(403),
        );
        $gitLabClient = $this->createMock(GitLabClient::class);
        $gitLabClient->method('getCommit')->willThrowException($forbidden);

        $this->expectExceptionObject($forbidden);
        (new GetProjectContainsAction($gitLabClient))('tool', 'd6ef1a7');
    }

    /**
     * @param string[] $tags
     * @param string[] $expected
     */
    #[DataProvider('tagOrderProvider')]
    public function testTagsAreOrderedOldestFirst(array $tags, array $expected): void
    {
        $action = new GetProjectContainsAction($this->gitLabClientWithRefs([], $tags));

        self::assertSame($expected, $action('tool', 'd6ef1a7')->tags);
    }

    /**
     * @return array<string, array{string[], string[]}>
     */
    public static function tagOrderProvider(): array
    {
        return [
            'semantic versions with pre-releases' => [
                ['1.0.0', '1.0.0-rc1', '1.0.0-beta12', '1.0.0-beta2', '1.0.0-alpha1', '1.1.0', '1.0.1'],
                ['1.0.0-alpha1', '1.0.0-beta2', '1.0.0-beta12', '1.0.0-rc1', '1.0.0', '1.0.1', '1.1.0'],
            ],
            'double-digit components' => [
                ['2.0.10', '2.0.9', '2.10.0', '2.9.0'],
                ['2.0.9', '2.0.10', '2.9.0', '2.10.0'],
            ],
            'legacy core prefix' => [
                ['8.x-1.10', '8.x-1.0', '8.x-1.0-beta1', '8.x-1.9', '8.x-1.0-unstable1'],
                ['8.x-1.0-unstable1', '8.x-1.0-beta1', '8.x-1.0', '8.x-1.9', '8.x-1.10'],
            ],
            'legacy tags before the semantic versions that followed them' => [
                ['2.0.0', '8.x-1.5', '2.0.0-rc1', '8.x-1.12'],
                ['8.x-1.5', '8.x-1.12', '2.0.0-rc1', '2.0.0'],
            ],
            'older core compatibility first' => [
                ['8.x-1.0', '7.x-2.3', '7.x-1.0'],
                ['7.x-1.0', '7.x-2.3', '8.x-1.0'],
            ],
            'pre-release suffixes with other separators' => [
                ['6.0.0', '6.0.0-alpha-4', '6.0.0-beta1', '6.0.0-alpha-3'],
                ['6.0.0-alpha-3', '6.0.0-alpha-4', '6.0.0-beta1', '6.0.0'],
            ],
            'legacy pre-release with a dot separator' => [
                ['8.x-5.7', '8.x-5.7.beta1', '8.x-5.6'],
                ['8.x-5.6', '8.x-5.7.beta1', '8.x-5.7'],
            ],
            'non-release tags last' => [
                ['previous-stable', '1.0.0', 'drupalcon-demo', '1.0.0-beta1'],
                ['1.0.0-beta1', '1.0.0', 'drupalcon-demo', 'previous-stable'],
            ],
        ];
    }
}
