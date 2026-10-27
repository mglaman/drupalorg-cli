<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\GitLab;

use GuzzleHttp\Exception\ClientException;
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

        $alreadyLinked = false;
        try {
            $this->gitLabClient->postIssueLink(
                $sourceProjectId,
                $source->issueId,
                $targetProjectId,
                $target->issueId,
                $linkType,
            );
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            $reason = self::gitLabErrorMessage($e);
            if ($status === 409) {
                $alreadyLinked = true;
            } elseif ($status === 403 && str_contains($reason, 'insufficient_scope')) {
                throw new \RuntimeException(sprintf(
                    'GitLab refused the link: your token needs the "api" scope. %s',
                    $reason,
                ), 403, $e);
            } elseif ($status === 403) {
                throw new \RuntimeException(self::forbiddenMessage($source, $target, $linkType, $reason), 403, $e);
            } else {
                throw self::linkFailed($source, $target, $reason, $e);
            }
        } catch (\Exception $e) {
            throw self::linkFailed($source, $target, $e->getMessage(), $e);
        }

        return new WorkItemLinkResult(
            sourceProjectPath: $source->projectPath,
            sourceIid: $source->issueId,
            linkType: $linkType->value,
            targetProjectPath: $target->projectPath,
            targetIid: $target->issueId,
            alreadyLinked: $alreadyLinked,
        );
    }

    private static function linkFailed(WorkItemRef $source, WorkItemRef $target, string $reason, \Exception $previous): \RuntimeException
    {
        return new \RuntimeException(sprintf(
            'Failed to link %s#%d to %s#%d: %s',
            $source->projectPath,
            $source->issueId,
            $target->projectPath,
            $target->issueId,
            $reason,
        ), 0, $previous);
    }

    /**
     * GitLab answers errors with {"message": ...} or, for token problems,
     * {"error": ..., "error_description": ...}.
     */
    private static function gitLabErrorMessage(ClientException $e): string
    {
        $body = (string) $e->getResponse()->getBody();
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $body !== '' ? $body : $e->getResponse()->getReasonPhrase();
        }
        if (isset($data['error']) && is_string($data['error'])) {
            $description = isset($data['error_description']) && is_string($data['error_description'])
                ? ': ' . $data['error_description']
                : '';
            return $data['error'] . $description;
        }
        $message = $data['message'] ?? $body;
        return is_string($message) ? $message : (string) json_encode($message);
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

    private static function forbiddenMessage(WorkItemRef $source, WorkItemRef $target, IssueLinkType $linkType, string $reason): string
    {
        $sourceArgument = self::refArgument($source);
        $targetArgument = self::refArgument($target);
        [$sourceNote, $targetNote] = match ($linkType) {
            IssueLinkType::RelatesTo => ['Related to', 'Related to'],
            IssueLinkType::Blocks => ['Blocks', 'Blocked by'],
            IssueLinkType::IsBlockedBy => ['Blocked by', 'Blocks'],
        };
        return sprintf(
            "GitLab refused the link: %1\$s\n"
            . "Linking needs membership in the projects of both work items. Post a comment on each instead:\n"
            . "  drupalorg issue:comment '%2\$s' --message=\"%4\$s %3\$s.\"\n"
            . "  drupalorg issue:comment '%3\$s' --message=\"%5\$s %2\$s.\"",
            $reason,
            $sourceArgument,
            $targetArgument,
            $sourceNote,
            $targetNote,
        );
    }

    /**
     * Shorthand for Drupal.org projects, the full URL for any other namespace,
     * since shorthand assumes the "project/" namespace.
     */
    private static function refArgument(WorkItemRef $ref): string
    {
        if (str_starts_with($ref->projectPath, 'project/')) {
            return $ref->projectMachineName() . '#' . $ref->issueId;
        }
        return sprintf('https://git.drupalcode.org/%s/-/work_items/%d', $ref->projectPath, $ref->issueId);
    }
}
