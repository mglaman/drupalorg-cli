<?php

namespace mglaman\DrupalOrg\Action\MergeRequest;

use mglaman\DrupalOrg\Result\MergeRequest\MergeRequestFilesResult;

class GetMergeRequestFilesAction extends AbstractMergeRequestAction
{
    public function __invoke(string $nid, int $mrIid): MergeRequestFilesResult
    {
        [$projectId] = $this->resolveGitLabProject($nid);

        $diffs = $this->gitLabClient->getMergeRequestDiffs($projectId, $mrIid);

        $files = array_map(static function (\stdClass $d): array {
            return [
                'path' => (string) $d->new_path,
                'new_file' => (bool) ($d->new_file ?? false),
                'deleted_file' => (bool) ($d->deleted_file ?? false),
                'renamed_file' => (bool) ($d->renamed_file ?? false),
            ];
        }, $diffs);

        return new MergeRequestFilesResult(
            iid: $mrIid,
            files: array_values($files),
        );
    }
}
