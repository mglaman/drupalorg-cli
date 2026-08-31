<?php

namespace mglaman\DrupalOrg\Action\Issue;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\IssueProjectResolver;
use mglaman\DrupalOrg\Result\Issue\IssueForkResult;

class GetIssueForkAction implements ActionInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    /**
     * @param string|null $projectMachineName
     *   Project from an explicit qualifier; skips the Drupal.org lookup.
     * @param string|null $repositoryProject
     *   Project of the git repository the command runs in.
     *
     * @see IssueProjectResolver for the resolution order.
     */
    public function __invoke(
        string $nid,
        ?string $projectMachineName = null,
        ?string $repositoryProject = null,
    ): IssueForkResult {
        $projectMachineName = (new IssueProjectResolver($this->client))
            ->resolve($nid, $projectMachineName, $repositoryProject);
        $remoteName = $projectMachineName . '-' . $nid;
        $gitLabProjectPath = 'issue/' . $remoteName;

        $sshUrl = 'git@git.drupal.org:' . $gitLabProjectPath . '.git';
        $httpsUrl = 'https://git.drupalcode.org/' . $gitLabProjectPath . '.git';

        $branches = [];
        try {
            $project = $this->gitLabClient->getProject($gitLabProjectPath);
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
