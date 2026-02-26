<?php

namespace mglaman\DrupalOrg\Action\Issue;

use mglaman\DrupalOrg\Action\ActionInterface;
use mglaman\DrupalOrg\Client;
use mglaman\DrupalOrg\GitLab\Client as GitLabClient;
use mglaman\DrupalOrg\Result\Issue\SetupIssueRemoteResult;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SetupIssueRemoteAction implements ActionInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly GitLabClient $gitLabClient,
    ) {
    }

    public function __invoke(string $nid): SetupIssueRemoteResult
    {
        $getFork = new GetIssueForkAction($this->client, $this->gitLabClient);
        $fork = $getFork($nid);

        $remoteName = $fork->remoteName;
        $sshUrl = $fork->sshUrl;

        // Check if the remote already exists.
        $checkProcess = new Process(['git', 'remote', 'get-url', $remoteName]);
        $checkProcess->run();
        $alreadyExists = $checkProcess->isSuccessful();

        if (!$alreadyExists) {
            $addProcess = new Process(['git', 'remote', 'add', $remoteName, $sshUrl]);
            try {
                $addProcess->mustRun();
            } catch (ProcessFailedException $e) {
                throw new \RuntimeException(
                    sprintf('Failed to add remote %s: %s', $remoteName, $e->getMessage()),
                    0,
                    $e
                );
            }
        }

        $fetchProcess = new Process(['git', 'fetch', $remoteName]);
        $fetchProcess->run();
        $fetchOutput = $fetchProcess->getOutput() . $fetchProcess->getErrorOutput();

        return new SetupIssueRemoteResult(
            remoteName: $remoteName,
            alreadyExists: $alreadyExists,
            fetchOutput: trim($fetchOutput),
        );
    }
}
