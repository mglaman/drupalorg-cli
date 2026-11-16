<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\Issue\IssueTemplateResult;

/**
 * Fetches a project's GitLab issue description template.
 *
 * GitLab puts the Default template in the description of any issue created
 * without one, so callers fill it in before `issue:create` instead.
 */
class GetIssueTemplateAction
{
    use ResolvesGitLabProjectTrait;

    public const DEFAULT_TEMPLATE = 'Default';

    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    public function __invoke(string $project, ?string $name = null): IssueTemplateResult
    {
        $projectPath = $this->resolveGitLabProjectPath($project);

        try {
            $available = $this->gitLabClient->getIssueTemplateNames($projectPath);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                'Failed to list the issue templates of %s: %s',
                $projectPath,
                $e->getMessage(),
            ), 0, $e);
        }

        if ($name === null) {
            if (!in_array(self::DEFAULT_TEMPLATE, $available, true)) {
                return new IssueTemplateResult($projectPath, null, '', $available);
            }
            $name = self::DEFAULT_TEMPLATE;
        } elseif (!in_array($name, $available, true)) {
            throw new \InvalidArgumentException(sprintf(
                '%s has no issue template named "%s". Available: %s.',
                $projectPath,
                $name,
                $available === [] ? '(none)' : implode(', ', $available),
            ));
        }

        try {
            $content = $this->gitLabClient->getIssueTemplate($projectPath, $name);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                'Failed to fetch the %s issue template of %s: %s',
                $name,
                $projectPath,
                $e->getMessage(),
            ), 0, $e);
        }

        return new IssueTemplateResult($projectPath, $name, $content, $available);
    }
}
