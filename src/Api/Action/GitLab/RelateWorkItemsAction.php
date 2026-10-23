<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\GitLab\IssueLinkType;
use mglaman\DrupalOrg\GitLab\WorkItemRef;
use mglaman\DrupalOrg\GitLab\WorkItemRefResolver;
use mglaman\DrupalOrg\Result\Issue\WorkItemLinkResult;

/**
 * Links two GitLab work items, in the same project or across projects.
 */
class RelateWorkItemsAction
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    public function __invoke(string $sourceRefOrNid, string $targetRefOrNid, IssueLinkType $linkType): WorkItemLinkResult
    {
        $resolver = new WorkItemRefResolver($this->client);
        $source = $resolver->resolve($sourceRefOrNid);
        $target = $resolver->resolve($targetRefOrNid);
        if ($source->projectPath === $target->projectPath && $source->issueId === $target->issueId) {
            throw new \InvalidArgumentException('Cannot link a work item to itself.');
        }

        $sourceProjectId = $this->projectId($source->projectPath);
        $targetProjectId = $source->projectPath === $target->projectPath
            ? $sourceProjectId
            : $this->projectId($target->projectPath);

        try {
            $this->gitLabClient->postIssueLink(
                $sourceProjectId,
                $source->issueId,
                $targetProjectId,
                $target->issueId,
                $linkType,
            );
        } catch (\Exception $e) {
            if ($e->getCode() === 403) {
                throw new \RuntimeException(self::forbiddenMessage($source, $target), 403, $e);
            }
            throw new \RuntimeException(sprintf(
                'Failed to link %s#%d to %s#%d: %s',
                $source->projectPath,
                $source->issueId,
                $target->projectPath,
                $target->issueId,
                $e->getMessage(),
            ), 0, $e);
        }

        return new WorkItemLinkResult(
            sourceProjectPath: $source->projectPath,
            sourceIid: $source->issueId,
            linkType: $linkType->value,
            targetProjectPath: $target->projectPath,
            targetIid: $target->issueId,
        );
    }

    private function projectId(string $projectPath): int
    {
        try {
            $project = $this->gitLabClient->getProject($projectPath);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                'Failed to look up GitLab project %s: %s',
                $projectPath,
                $e->getMessage(),
            ), 0, $e);
        }
        if (!isset($project->id) || !is_numeric($project->id)) {
            throw new \RuntimeException(sprintf(
                'GitLab project response for %s did not contain an id.',
                $projectPath,
            ));
        }
        return (int) $project->id;
    }

    private static function forbiddenMessage(WorkItemRef $source, WorkItemRef $target): string
    {
        $sourceShorthand = $source->projectMachineName() . '#' . $source->issueId;
        $targetShorthand = $target->projectMachineName() . '#' . $target->issueId;
        return sprintf(
            "GitLab refused the link: you must be a member of the projects of both work items.\n"
            . "Post a comment on each instead:\n"
            . "  drupalorg issue:comment '%1\$s' --message=\"Related to %2\$s.\"\n"
            . "  drupalorg issue:comment '%2\$s' --message=\"Related to %1\$s.\"",
            $sourceShorthand,
            $targetShorthand,
        );
    }
}
