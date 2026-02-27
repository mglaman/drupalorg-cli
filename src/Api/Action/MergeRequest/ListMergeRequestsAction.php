<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\Enum\MergeRequestState;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestItem;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestListResult;

class ListMergeRequestsAction extends AbstractMergeRequestAction
{
    public function __invoke(string $nid, MergeRequestState $state = MergeRequestState::Opened): MergeRequestListResult
    {
        [$projectId, $gitLabProjectPath] = $this->resolveGitLabProject($nid);

        $params = ['per_page' => 100];
        if ($state !== MergeRequestState::All) {
            $params['state'] = $state->value;
        }

        $mrObjects = $this->gitLabClient->getMergeRequests($projectId, $params);
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
