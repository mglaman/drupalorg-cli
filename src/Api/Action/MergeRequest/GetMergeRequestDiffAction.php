<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestDiffResult;

class GetMergeRequestDiffAction extends AbstractMergeRequestAction
{
    public function __invoke(string $nid, int $mrIid): MergeRequestDiffResult
    {
        [$projectId] = $this->resolveGitLabProject($nid);

        $mr = $this->gitLabClient->getMergeRequest($projectId, $mrIid);
        $diffs = $this->gitLabClient->getMergeRequestDiffs($projectId, $mrIid);

        $unified = '';
        foreach ($diffs as $fileDiff) {
            $unified .= $fileDiff->diff ?? '';
        }

        return new MergeRequestDiffResult(
            iid: $mrIid,
            sourceBranch: (string) $mr->source_branch,
            targetBranch: (string) $mr->target_branch,
            diff: $unified,
        );
    }
}
