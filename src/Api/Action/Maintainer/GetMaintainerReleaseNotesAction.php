<?php

namespace mglaman\DrupalOrg\Action\Maintainer;

use CzProject\GitPhp\GitRepository;
use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\CommitParser;
use mglaman\DrupalOrg\DrupalOrg;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
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

    public function __construct(
        private readonly Client $client,
        private readonly ?GitLabClient $gitLabClient = null,
    ) {
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
            $exitCode = $process->getExitCode();
            $errorOutput = trim($process->getErrorOutput());
            $message = 'Error getting commit log';
            if ($exitCode !== null) {
                $message .= sprintf(' (exit code %d)', $exitCode);
            }
            if ($errorOutput !== '') {
                $message .= sprintf(': %s', $errorOutput);
            }
            throw new \RuntimeException($message);
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

        // Projects migrated to GitLab work items report field_project_has_issue_queue
        // as false. Work item iids are unique only within a project, so they must
        // not be treated as global Drupal.org node IDs.
        $projectEntity = $drupalOrg->getProject($project);
        $projectId = $projectEntity?->nid;
        $isGitLab = $projectEntity !== null && !$projectEntity->hasIssueQueue;
        // The resolved machine name scopes GitLab work item URLs; fall back to the
        // git-derived name when the project could not be looked up.
        $machineName = ($projectEntity !== null && $projectEntity->machineName !== '')
            ? $projectEntity->machineName
            : $project;

        // Contribution records are filtered by the issue's source link: a node URL
        // for legacy issues, a work item URL for GitLab projects.
        $sourceLinks = [];
        foreach ($nidList as $nid) {
            $sourceLinks[$nid] = $isGitLab
                ? sprintf('https://git.drupalcode.org/project/%s/-/work_items/%s', $machineName, $nid)
                : sprintf('https://www.drupal.org/node/%s', $nid);
        }
        $contributorsFromApi = $drupalOrg->getContributorsFromJsonApi($sourceLinks);

        $issueDetails = $isGitLab ? [] : $drupalOrg->getIssueDetails($nidList);
        $gitLabIssues = [];
        if ($isGitLab) {
            $gitLabClient = $this->gitLabClient ?? new GitLabClient();
            $gitLabIssues = $gitLabClient->getIssuesByIid('project/' . $machineName, array_map('intval', $nidList));
        }

        // Track all contributors across commits.
        $users = [];

        // Map each issue ID to its resolved canonical URL.
        $issueLinks = [];

        // Process commits into categorized changes.
        $categorizedChanges = [];
        foreach ($commits as $commit) {
            $nid = CommitParser::getNid($commit->title);

            // Determine issue category and link.
            $issueCategoryLabel = 'Misc';
            if ($nid !== null) {
                if ($isGitLab) {
                    $issue = $gitLabIssues[(int) $nid] ?? null;
                    $link = sprintf('https://www.drupal.org/project/%s/issues/%s', $machineName, $nid);
                    if ($issue !== null) {
                        $issueCategoryLabel = self::categoryFromGitLabLabels($issue->labels)
                            ?? CommitParser::categoryFromConventionalCommit($commit->title)
                            ?? 'Misc';
                        if ($issue->webUrl !== '') {
                            $link = $issue->webUrl;
                        }
                    } else {
                        $issueCategoryLabel = CommitParser::categoryFromConventionalCommit($commit->title) ?? 'Misc';
                    }
                } elseif (isset($issueDetails[$nid])) {
                    $issueCategory = $issueDetails[$nid]->fieldIssueCategory;
                    $issueCategoryLabel = self::CATEGORY_MAP[$issueCategory] ?? 'Misc';
                    $link = sprintf('https://www.drupal.org/node/%s', $nid);
                } else {
                    $issueCategoryLabel = CommitParser::categoryFromConventionalCommit($commit->title) ?? 'Misc';
                    $link = sprintf('https://www.drupal.org/node/%s', $nid);
                }
                $issueLinks[$nid] = $link;
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
            issueLinks: $issueLinks,
            isGitLab: $isGitLab,
        );
    }

    /**
     * Resolve an issue category from a GitLab `category::*` label.
     *
     * @param string[] $labels
     */
    private static function categoryFromGitLabLabels(array $labels): ?string
    {
        foreach ($labels as $label) {
            $matches = [];
            if (preg_match('/^category::(.+)$/i', $label, $matches) === 1) {
                return match (strtolower(trim($matches[1]))) {
                    'bug' => self::CATEGORY_MAP[1],
                    'task' => self::CATEGORY_MAP[2],
                    'feature' => self::CATEGORY_MAP[3],
                    'support' => self::CATEGORY_MAP[4],
                    'plan' => self::CATEGORY_MAP[5],
                    default => null,
                };
            }
        }
        return null;
    }

    private function cleanCommitTitle(string $title): string
    {
        // Strip common prefixes.
        $title = preg_replace('/^(Patch |- |Issue ){0,3}/', '', $title) ?? $title;
        // Strip any trailing "by username:" attribution.
        $title = preg_replace('/\s+by [^:]+:.*$/S', '', $title) ?? $title;
        return $title;
    }

    private function getProjectName(string $cwd): string
    {
        $process = new Process(['git', 'config', '--get', 'remote.origin.url'], $cwd);
        $process->run();

        if ($process->getExitCode() !== 0) {
            return '';
        }

        $remoteUrl = trim($process->getOutput());

        // GitLab-hosted projects (including those on work items) push to
        // git.drupalcode.org/project/{name} or, for issue forks,
        // git.drupalcode.org/issue/{name}-{nid}. Machine names use no hyphens,
        // so the capture stops before the issue fork's "-{nid}" suffix.
        if (str_contains($remoteUrl, 'git.drupalcode.org')) {
            $matches = [];
            if (preg_match('#git\.drupalcode\.org[:/](?:project|issue)/([a-z0-9_]+)#', $remoteUrl, $matches) === 1) {
                return $matches[1];
            }
        }

        // Not a drupal.org project — use the directory name.
        if (strpos($remoteUrl, 'drupal.org') === false) {
            $parts = explode(DIRECTORY_SEPARATOR, $cwd);
            return end($parts);
        }

        // Sandbox projects cannot have releases.
        if (strpos($remoteUrl, 'drupal.org/sandbox') !== false) {
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
