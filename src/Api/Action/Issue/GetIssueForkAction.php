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

        try {
            $project = $this->gitLabClient->getProject($gitLabProjectPath);
        } catch (\Exception $e) {
            // GitLab answers 404 until someone clicks "Create issue fork".
            $project = null;
        }

        $branches = [];
        if ($project !== null) {
            try {
                $branches = array_map(
                    static fn(\stdClass $b) => (string) $b->name,
                    $this->gitLabClient->getBranches((int) $project->id)
                );
            } catch (\Exception $e) {
                // The fork exists; a failed branch listing must not report it missing.
            }
        }

        return new IssueForkResult(
            remoteName: $remoteName,
            sshUrl: $sshUrl,
            httpsUrl: $httpsUrl,
            gitLabProjectPath: $gitLabProjectPath,
            exists: $project !== null,
            branches: $branches,
        );
    }
}
