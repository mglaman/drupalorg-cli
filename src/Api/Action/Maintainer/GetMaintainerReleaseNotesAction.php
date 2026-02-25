<?php

namespace mglaman\DrupalOrg\Action\Maintainer;

use CzProject\GitPhp\GitRepository;
use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\CommitParser;
use mglaman\DrupalOrg\DrupalOrg;
use mglaman\DrupalOrg\Result\Maintainer\MaintainerReleaseNotesResult;
use Symfony\Component\Process\Process;

class GetMaintainerReleaseNotesAction implements ActionInterface
{
    /**
     * @var array<int, string>
     */
    private const CATEGORY_MAP = [
        0 => 'Misc',
        1 => 'Bug',
        2 => 'Task',
        3 => 'Feature',
        4 => 'Support',
        5 => 'Plan',
    ];

    public function __construct(private readonly Client $client)
    {
    }

    public function __invoke(
        GitRepository $repository,
        string $cwd,
        string $ref1,
        string $ref2
    ): MaintainerReleaseNotesResult {
        $tags = $repository->getTags() ?? [];

        if (!in_array($ref1, $tags, true)) {
            throw new \InvalidArgumentException(sprintf('The %s tag is not valid.', $ref1));
        }
        if (($ref2 !== 'HEAD') && !in_array($ref2, $tags, true)) {
            throw new \InvalidArgumentException(sprintf('The %s tag is not valid.', $ref2));
        }

        $process = new Process([
            'git',
            'log',
            '-s',
            '--format=%x00%s%x1f%ae%x1f%ce%x1f%b',
            "$ref1..$ref2",
        ], $cwd);
        $process->run();

        if ($process->getExitCode() !== 0) {
            throw new \RuntimeException('Error getting commit log');
        }

        // Parse commits into structured objects.
        $commits = [];
        $commitBlocks = array_filter(explode("\x00", $process->getOutput()));
        foreach ($commitBlocks as $block) {
            $parts = explode("\x1f", $block, 4);
            $commit = new \stdClass();
            $commit->title = trim($parts[0]);
            $commit->author_email = trim($parts[1] ?? '');
            $commit->committer_email = trim($parts[2] ?? '');
            $commit->message = $parts[3] ?? '';
            $commits[] = $commit;
        }

        // Extract all NIDs from commit titles.
        $nids = [];
        foreach ($commits as $commit) {
            $nid = CommitParser::getNid($commit->title);
            if ($nid !== null && !isset($nids[$nid])) {
                $nids[$nid] = $nid;
            }
        }

        // Work out what the project name is.
        $project = $this->getProjectName($cwd);
        if ($project === '') {
            throw new \RuntimeException('Could not determine project name.');
        }

        // Fetch data from Drupal.org concurrently.
        $drupalOrg = new DrupalOrg($this->client->getGuzzleClient());
        $nidList = array_values($nids);
        $contributorsFromApi = $drupalOrg->getContributorsFromJsonApi($nidList);
        $issueDetails = $drupalOrg->getIssueDetails($nidList);
        $projectId = $drupalOrg->getProjectId($project);

        // Track all contributors across commits.
        $users = [];

        // Process commits into categorized changes.
        $categorizedChanges = [];
        foreach ($commits as $commit) {
            $nid = CommitParser::getNid($commit->title);

            // Determine issue category.
            $issueCategoryLabel = 'Misc';
            if ($nid !== null && isset($issueDetails[$nid])) {
                $issueCategory = $issueDetails[$nid]->fieldIssueCategory;
                $issueCategoryLabel = self::CATEGORY_MAP[$issueCategory] ?? 'Misc';
            }

            // Gather contributors: JSON:API first, then commit parsing, then email fallback.
            $commitContributors = [];
            if ($nid !== null && isset($contributorsFromApi[$nid]) && $contributorsFromApi[$nid] !== []) {
                $commitContributors = $contributorsFromApi[$nid];
            } else {
                $commitContributors = CommitParser::extractUsernames($commit);
                if ($commitContributors === []) {
                    foreach ([$commit->author_email, $commit->committer_email] as $email) {
                        if (preg_match('/(?<=[0-9]-)([a-zA-Z0-9\-_\.]{2,255})(?=@users\.noreply\.drupalcode\.org)/', $email, $m)) {
                            $commitContributors[] = $m[1];
                        }
                    }
                    $commitContributors = array_values(array_unique($commitContributors));
                }
            }

            foreach ($commitContributors as $username) {
                if (!isset($users[$username])) {
                    $users[$username] = 1;
                } else {
                    $users[$username]++;
                }
            }

            if ($nid !== null && !isset($categorizedChanges[$issueCategoryLabel][$nid])) {
                $categorizedChanges[$issueCategoryLabel][$nid] = $this->cleanCommitTitle($commit->title);
            }
        }
        ksort($categorizedChanges);

        // Fetch change records if we have a project ID.
        $changeRecords = [];
        if ($projectId !== null) {
            $changeRecords = $drupalOrg->getChangeRecords($projectId, $ref2);
        }

        return new MaintainerReleaseNotesResult(
            ref1: $ref1,
            ref2: $ref2,
            project: $project,
            categorizedChanges: $categorizedChanges,
            contributors: $users,
            nidList: $nidList,
            changeRecords: $changeRecords,
        );
    }

    private function cleanCommitTitle(string $title): string
    {
        // Strip common prefixes.
        $title = preg_replace('/^(Patch |- |Issue ){0,3}/', '', $title);
        // Strip any trailing "by username:" attribution.
        $title = preg_replace('/\s+by [^:]+:.*$/S', '', $title);
        return $title;
    }

    private function getProjectName(string $cwd): string
    {
        $process = new Process(['git', 'config', '--get', 'remote.origin.url'], $cwd);
        $process->run();

        if ($process->getExitCode() !== 0) {
            return '';
        }

        $remoteUrl = $process->getOutput();

        // Not a drupal.org project — use the directory name.
        if (!strpos($remoteUrl, 'drupal.org')) {
            $parts = explode(DIRECTORY_SEPARATOR, $cwd);
            return end($parts);
        }

        // Sandbox projects cannot have releases.
        if (strpos($remoteUrl, 'drupal.org/sandbox')) {
            return '';
        }

        $path = str_replace('.git', '', $remoteUrl);
        if ($path === '') {
            return '';
        }
        $path = explode('/', $path);
        return trim(array_pop($path));
    }
}
