<?php

namespace mglaman\DrupalOrg\Action\Issue;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\Issue\IssueForkResult;

class GetIssueForkAction implements ActionInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    public function __invoke(string $nid): IssueForkResult
    {
        $issue = $this->client->getNode($nid);
        $projectMachineName = $issue->fieldProjectMachineName;
        $remoteName = $projectMachineName . '-' . $nid;
        $gitLabProjectPath = 'issue/' . $remoteName;

        $sshUrl = 'git@git.drupal.org:' . $gitLabProjectPath . '.git';
        $httpsUrl = 'https://git.drupalcode.org/' . $gitLabProjectPath . '.git';

        $branches = [];
        try {
            $encodedPath = urlencode($gitLabProjectPath);
            $project = $this->gitLabClient->getProject($encodedPath);
            $branchObjects = $this->gitLabClient->getBranches((int) $project->id);
            $branches = array_map(
                static fn(\stdClass $b) => (string) $b->name,
                $branchObjects
            );
        } catch (\Exception $e) {
            // Fork may not exist yet; return URL info without branches.
        }

        return new IssueForkResult(
            remoteName: $remoteName,
            sshUrl: $sshUrl,
            httpsUrl: $httpsUrl,
            gitLabProjectPath: $gitLabProjectPath,
            branches: $branches,
        );
    }
}
