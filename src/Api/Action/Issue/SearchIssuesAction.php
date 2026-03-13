<?php

declare(strict_types=1);

namespace mglaman\DrupalOrg\Action\Issue;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Entity\Project;
use mglaman\DrupalOrg\Request;
use mglaman\DrupalOrg\Result\Issue\IssueSearchResult;

class SearchIssuesAction implements ActionInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param int[] $statuses
     */
    public function __invoke(Project $project, string $query, array $statuses, int $limit): IssueSearchResult
    {
        $params = [
            'type' => 'project_issue',
            'field_project' => $project->nid,
            'sort' => 'changed',
            'direction' => 'DESC',
            // Fetch more than $limit so the in-memory title filter has enough
            // candidates; capped at 100 to avoid excessively large responses.
            'limit' => min($limit * 3, 100),
        ];
        if ($statuses !== []) {
            $params['field_issue_status[value]'] = $statuses;
        }
        $rawIssues = $this->client->requestRaw(new Request('node.json', $params));
        $issueList = (array) ($rawIssues->list ?? []);
        $issues = array_map(
            static fn(\stdClass $issue) => IssueNode::fromStdClass($issue),
            $issueList
        );

        $issues = array_filter(
            $issues,
            static fn(IssueNode $issue) => stripos($issue->title, $query) !== false
        );
        $issues = array_slice(array_values($issues), 0, $limit);

        return new IssueSearchResult(
            projectTitle: $project->title,
            issues: $issues,
        );
    }
}
