<?php

namespace mglaman\DrupalOrg\Action\Issue;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\Entity\File;
use mglaman\DrupalOrg\Entity\IssueFile;
use mglaman\DrupalOrg\Entity\IssueNode;
use mglaman\DrupalOrg\Result\Issue\IssuePatchResult;

class GetLatestIssuePatchAction implements ActionInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    public function __invoke(string $nid): IssuePatchResult
    {
        $issue = $this->client->getNode($nid);
        $file = $this->getLatestFile($issue);
        if ($file === null) {
            throw new \RuntimeException('No patch file found for issue ' . $nid);
        }
        return IssuePatchResult::fromIssueNodeAndFile($issue, $file);
    }

    private function getLatestFile(IssueNode $issue): ?File
    {
        $files = array_filter(
            $issue->fieldIssueFiles,
            static function (IssueFile $value): bool {
                return $value->display;
            }
        );
        $files = array_reverse($files);
        $files = array_map(
            function (IssueFile $value): File {
                return $this->client->getFile($value->fileId);
            },
            $files
        );
        $files = array_filter(
            $files,
            static function (File $file): bool {
                return str_contains($file->name, '.patch') && !str_contains($file->name, 'do-not-test');
            }
        );
        return count($files) > 0 ? reset($files) : null;
    }
}
