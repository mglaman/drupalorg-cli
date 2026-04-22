<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\GitLab\MergeRequestRef;
use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestDiffResult;

class GetMergeRequestDiffAction extends AbstractMergeRequestAction
{
    public function __invoke(string $nid, int $mrIid, ?MergeRequestRef $ref = null): MergeRequestDiffResult
    {
        [$projectId] = $ref !== null ? $this->resolveFromRef($ref) : $this->resolveGitLabProject($nid);

        $mr = $this->gitLabClient->getMergeRequest($projectId, $mrIid);
        $diffs = $this->gitLabClient->getMergeRequestDiffs($projectId, $mrIid);

        $unified = '';
        foreach ($diffs as $fileDiff) {
            $diff = $fileDiff->diff ?? '';
            if ($diff === '') {
                continue;
            }
            $unified .= $diff;
            if (!str_ends_with($diff, "\n")) {
                $unified .= "\n";
            }
        }

        return new MergeRequestDiffResult(
            iid: $mrIid,
            sourceBranch: (string) $mr->source_branch,
            targetBranch: (string) $mr->target_branch,
            diff: $unified,
        );
    }
}
