<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Enum\ProjectIssueCategory;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\Result\Issue\WorkItemCreatedResult;

/**
 * Creates a GitLab work item in a project's issue queue.
 *
 * Only projects whose issue queue moved to GitLab can take new issues this
 * way. Drupal.org has no write API for the legacy issue queue, so those
 * projects get an error pointing at the web form.
 */
class CreateWorkItemAction
{
    use ResolvesGitLabProjectTrait;

    private const DEFAULT_TEMPLATE = 'Default';

    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    /**
     * @param string[] $labels
     */
    public function __invoke(
        string $project,
        string $title,
        string $description = '',
        ?ProjectIssueCategory $category = null,
        array $labels = [],
        bool $dryRun = false,
        bool $confidential = false,
    ): WorkItemCreatedResult {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('The issue title is empty.');
        }

        $projectPath = $this->resolveGitLabProjectPath($project);

        $labels = array_values(array_unique(array_filter(
            array_map('trim', [...($category !== null ? [$category->gitLabLabel()] : []), ...$labels]),
            static fn(string $label) => $label !== '',
        )));
        $this->assertLabelsExist($projectPath, $labels);

        if ($dryRun) {
            return new WorkItemCreatedResult(
                projectPath: $projectPath,
                title: $title,
                description: $description,
                labels: $labels,
                confidential: $confidential,
                usesDefaultTemplate: $description === '' && $this->hasDefaultTemplate($projectPath),
                possibleDuplicates: $this->findOpenIssuesWithTitle($projectPath, $title),
            );
        }

        try {
            $response = $this->gitLabClient->createIssue($projectPath, $title, $description, $labels, $confidential);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                'Failed to create an issue in %s: %s',
                $projectPath,
                $e->getMessage(),
            ), 0, $e);
        }

        if (!isset($response->iid) || !is_numeric($response->iid)) {
            throw new \RuntimeException(sprintf(
                'GitLab issue response for %s did not contain an iid. The '
                . 'issue may or may not have been created; check the project '
                . 'before retrying.',
                $projectPath,
            ));
        }

        return new WorkItemCreatedResult(
            projectPath: $projectPath,
            title: $title,
            description: $description,
            labels: $labels,
            issueIid: (int) $response->iid,
            webUrl: isset($response->web_url) && is_string($response->web_url) ? $response->web_url : null,
            confidential: $confidential,
            usesDefaultTemplate: $description === '' && isset($response->description) && is_string($response->description) && $response->description !== '',
        );
    }

    /**
     * Refuses labels the project does not have.
     *
     * GitLab drops or creates unknown labels depending on the caller's role,
     * so a typo would otherwise go unnoticed. When the label list cannot be
     * read (no token, API error), the labels are sent unchecked.
     *
     * @param string[] $labels
     */
    private function assertLabelsExist(string $projectPath, array $labels): void
    {
        if ($labels === []) {
            return;
        }
        try {
            $known = $this->gitLabClient->getLabelNames($projectPath);
        } catch (\Exception) {
            return;
        }
        if ($known === []) {
            return;
        }
        $unknown = array_values(array_diff($labels, $known));
        if ($unknown === []) {
            return;
        }
        $messages = array_map(static function (string $label) use ($known): string {
            $suggestion = self::closestLabel($label, $known);
            return $suggestion === null ? $label : sprintf('%s (did you mean %s?)', $label, $suggestion);
        }, $unknown);
        throw new \InvalidArgumentException(sprintf(
            '%s has no label %s.',
            $projectPath,
            implode(', ', $messages),
        ));
    }

    /**
     * @param string[] $known
     */
    private static function closestLabel(string $label, array $known): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach ($known as $candidate) {
            $distance = levenshtein(strtolower($label), strtolower($candidate));
            if ($distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }
        return $bestDistance <= 3 ? $best : null;
    }

    private function hasDefaultTemplate(string $projectPath): bool
    {
        try {
            return in_array(self::DEFAULT_TEMPLATE, $this->gitLabClient->getIssueTemplateNames($projectPath), true);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Open issues with a near-identical title, so a dry run can flag
     * duplicates such as a retried create.
     *
     * GitLab's search matches substrings, so its hits are narrowed to titles
     * at least 80% similar to this one.
     *
     * @return GitLabIssue[]
     */
    private function findOpenIssuesWithTitle(string $projectPath, string $title): array
    {
        try {
            $issues = $this->gitLabClient->getIssues($projectPath, [
                'state' => 'opened',
                'search' => $title,
                'in' => 'title',
                'per_page' => 20,
            ]);
        } catch (\Exception) {
            return [];
        }
        $normalize = static fn(string $text) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
        $wanted = $normalize($title);
        $matches = [];
        foreach ($issues as $data) {
            $issue = GitLabIssue::fromStdClass($data);
            similar_text($wanted, $normalize($issue->title), $percent);
            if ($percent >= 80) {
                $matches[] = $issue;
            }
        }
        return $matches;
    }
}
