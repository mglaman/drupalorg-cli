<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestItem;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestListResult;

class ListMergeRequestsAction implements ActionInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    public function __invoke(string $nid, string $state = 'opened'): MergeRequestListResult
    {
        $issue = $this->client->getNode($nid);
        $projectMachineName = $issue->fieldProjectMachineName;
        $remoteName = $projectMachineName . '-' . $nid;
        $gitLabProjectPath = 'issue/' . $remoteName;

        $encodedPath = urlencode($gitLabProjectPath);
        $project = $this->gitLabClient->getProject($encodedPath);

        $params = ['per_page' => 100];
        if ($state !== 'all') {
            $params['state'] = $state;
        }

        $mrObjects = $this->gitLabClient->getMergeRequests((int) $project->id, $params);
        $mergeRequests = array_map(
            static fn(\stdClass $mr) => MergeRequestItem::fromStdClass($mr),
            $mrObjects
        );

        return new MergeRequestListResult(
            projectPath: $gitLabProjectPath,
            mergeRequests: $mergeRequests,
        );
    }
}
