<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\Project;

use GuzzleHttp\Exception\ClientException;
use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\Project\ProjectContainsResult;

class GetProjectContainsAction implements ActionInterface
{
    /**
     * Legacy contrib tags carry a core prefix, such as 7.x-2.0 or 8.x-1.0-beta1.
     */
    private const LEGACY_TAG = '/^(?<core>\d+(?:\.\d+)*)\.x-(?<version>.+)$/';

    /**
     * Semantic version tags, such as 2.0.0-rc1, started with Drupal 8 contrib,
     * so they sort alongside 8.x tags.
     */
    private const SEMVER_CORE = '8';

    /**
     * A version with an optional pre-release suffix: 1.0, 2.0.0-rc1, 1.0-unstable3.
     *
     * Also accepts suffixes that projects have tagged with other separators,
     * such as 6.0.0-alpha-3 and 8.x-5.7.beta1; version_compare() orders them.
     */
    private const VERSION = '/^\d+(?:\.\d+)*(?:[-.][a-z]+-?\d*)?$/i';

    public function __construct(private readonly GitLabClient $gitLabClient)
    {
    }

    /**
     * @throws \RuntimeException
     *   When the project has no commit matching the SHA.
     */
    public function __invoke(string $machineName, string $sha): ProjectContainsResult
    {
        $projectPath = 'project/' . $machineName;
        try {
            $commit = $this->gitLabClient->getCommit($projectPath, $sha);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new \RuntimeException("Commit $sha not found in project $machineName.", 404, $e);
            }
            throw $e;
        }

        $fullSha = (string) $commit->id;
        $branches = [];
        $tags = [];
        foreach ($this->gitLabClient->getCommitRefs($projectPath, $fullSha) as $ref) {
            if ($ref->type === 'tag') {
                $tags[] = (string) $ref->name;
            } else {
                $branches[] = (string) $ref->name;
            }
        }
        sort($branches, SORT_NATURAL);
        usort($tags, self::compareTags(...));

        return new ProjectContainsResult(
            projectName: $machineName,
            sha: $fullSha,
            title: (string) $commit->title,
            committedDate: (string) $commit->committed_date,
            webUrl: (string) $commit->web_url,
            branches: $branches,
            tags: $tags,
        );
    }

    /**
     * Orders release tags oldest first by core compatibility, then version.
     *
     * Tags that are not release versions sort last, by name.
     */
    private static function compareTags(string $a, string $b): int
    {
        $aRelease = self::parseReleaseTag($a);
        $bRelease = self::parseReleaseTag($b);
        if ($aRelease === null && $bRelease === null) {
            return strnatcmp($a, $b);
        }
        if ($aRelease === null) {
            return 1;
        }
        if ($bRelease === null) {
            return -1;
        }
        $coreOrder = version_compare($aRelease['core'], $bRelease['core']);
        if ($coreOrder !== 0) {
            return $coreOrder;
        }
        return version_compare($aRelease['version'], $bRelease['version']);
    }

    /**
     * @return array{core: string, version: string}|null
     */
    private static function parseReleaseTag(string $tag): ?array
    {
        $core = self::SEMVER_CORE;
        $version = $tag;
        if (preg_match(self::LEGACY_TAG, $tag, $matches) === 1) {
            $core = $matches['core'];
            $version = $matches['version'];
        }
        if (preg_match(self::VERSION, $version) !== 1) {
            return null;
        }
        return ['core' => $core, 'version' => $version];
    }
}
