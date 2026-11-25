<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Result\Issue;

use mglaman\DrupalOrg\GitLab\Entity\GitLabIssue;
use mglaman\DrupalOrg\Result\ResultInterface;

/**
 * A work item created on GitLab, or the payload a dry run would have sent.
 */
class WorkItemCreatedResult implements ResultInterface
{
    /**
     * @param string[] $labels
     * @param bool $usesDefaultTemplate
     *   No description was given and the project has a Default issue
     *   template, which GitLab puts in the description (or did).
     * @param GitLabIssue[] $possibleDuplicates
     *   Open issues with a near-identical title. Only checked on a dry run.
     */
    public function __construct(
        public readonly string $projectPath,
        public readonly string $title,
        public readonly string $description,
        public readonly array $labels,
        public readonly ?int $issueIid = null,
        public readonly ?string $webUrl = null,
        public readonly bool $confidential = false,
        public readonly bool $usesDefaultTemplate = false,
        public readonly array $possibleDuplicates = [],
    ) {
    }

    public function isDryRun(): bool
    {
        return $this->issueIid === null;
    }

    public function workItemUrl(): ?string
    {
        if ($this->webUrl !== null) {
            return $this->webUrl;
        }
        if ($this->issueIid === null) {
            return null;
        }
        return sprintf(
            'https://git.drupalcode.org/%s/-/work_items/%d',
            $this->projectPath,
            $this->issueIid,
        );
    }

    /**
     * Explains what happens to the description when none was given.
     */
    public function templateNotice(): ?string
    {
        if (!$this->usesDefaultTemplate) {
            return null;
        }
        if ($this->isDryRun()) {
            return sprintf(
                'No description given, so GitLab will use the project\'s Default issue template. '
                . 'Run `drupalorg issue:template %s`, fill it in (including any AI disclosure section), '
                . 'and pass it with --file.',
                $this->projectPath,
            );
        }
        return sprintf(
            'No description given, so GitLab filled in the project\'s Default issue template. '
            . 'Edit it at %s (including any AI disclosure section).',
            $this->workItemUrl(),
        );
    }

    /**
     * The shorthand other issue commands accept, e.g. `project/tool#3583107`.
     */
    public function ref(): ?string
    {
        if ($this->issueIid === null) {
            return null;
        }
        return sprintf('%s#%d', $this->projectPath, $this->issueIid);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'project_path' => $this->projectPath,
            'issue_iid' => $this->issueIid,
            'ref' => $this->ref(),
            'url' => $this->workItemUrl(),
            'title' => $this->title,
            'description' => $this->description,
            'labels' => $this->labels,
            'confidential' => $this->confidential,
            'uses_default_template' => $this->usesDefaultTemplate,
            'template_notice' => $this->templateNotice(),
            'possible_duplicates' => array_map(static fn(GitLabIssue $issue) => [
                'issue_iid' => $issue->iid,
                'title' => $issue->title,
                'url' => $issue->webUrl,
            ], $this->possibleDuplicates),
            'dry_run' => $this->isDryRun(),
        ];
    }
}
