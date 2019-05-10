<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use Gitter\Client;
use mglaman\DrupalOrg\RawResponse;
use mglaman\DrupalOrgCli\Git;
use mglaman\DrupalOrgCli\IssueNidArgumentTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Patch extends IssueCommandBase
{

    use IssueNidArgumentTrait;

    protected $cwd;

    /**
     * @var \mglaman\DrupalOrgCli\Git
     */
    private $git;

    protected function configure()
    {
        $this
        ->setName('issue:patch')
        ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
        ->setDescription('Generate a patch for the issue from committed local changes.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->cwd = getcwd();
        $this->git = new Git();
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $nid = $this->getNidArgument($this->stdIn);
        if ($nid === null) {
            $this->stdOut->writeln('Please provide an issue nid');
            return 1;
        }
        $issue = $this->getNode($nid);

        $workingCopy = $this->git->getWorkingCopy(getcwd());
        if ($workingCopy === null) {
            $this->stdOut->writeln('Not in a repository');
            return 1;
        }

        $branches = $workingCopy->getBranches()->all();
        $issue_version_branch = $this->getIssueVersionBranchName($issue);
        if (!in_array($issue_version_branch, $branches, true)) {
            $this->stdErr->writeln("Issue branch $issue_version_branch does not exist locally.");
            return 1;
        }

        // Create a diff from our merge-base commit.
        $mergeBaseCommit = trim($workingCopy->run('merge-base', [$issue_version_branch, 'HEAD']));
        $patchDiff = $workingCopy->diff($mergeBaseCommit, 'HEAD', ['no-prefix' => true, 'no-ext-diff' => true]);

        $patchName = $this->buildPatchName($issue);
        $filename = $this->cwd . DIRECTORY_SEPARATOR . $patchName;
        file_put_contents($filename, $patchDiff);
        $this->stdOut->writeln("<comment>Patch written to {$filename}</comment>");

        $diffStat = $workingCopy->diff($mergeBaseCommit, ['stat' => true]);
        $this->stdOut->write($diffStat);

        $interdiffCommand = $this->getApplication()->find('issue:interdiff');
        $interdiffCommand->run($this->stdIn, $this->stdOut);
    }

    protected function buildPatchName(RawResponse $issue)
    {
        $cleanTitle = $this->getCleanIssueTitle($issue);
        $comment_count = $issue->get('comment_count');
        return sprintf('%s-%s-%s.patch', $cleanTitle, $issue->get('nid'), $comment_count + 1);
    }
}
