<?php

namespace mglaman\DrupalOrgCli\Command\Issue;

use mglaman\DrupalOrg\RawResponse;
use mglaman\DrupalOrgCli\Git;
use mglaman\DrupalOrgCli\IssueNidArgumentTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to generate an interdiff text file for an issue.
 */
class Interdiff extends IssueCommandBase
{

    use IssueNidArgumentTrait;

    protected $cwd;

    /**
     * @var \mglaman\DrupalOrgCli\Git
     */
    private $git;

    public function __construct(Git $git)
    {
        parent::__construct();
        $this->git = $git;
        $this->cwd = getcwd();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
        ->setName('issue:interdiff')
        ->addArgument('nid', InputArgument::OPTIONAL, 'The issue node ID')
        ->setDescription('Generate an interdiff for the issue from local changes.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
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
        $issueVersion = $issue->get('field_issue_version');
        if (strpos($issueVersion, $workingCopy->getBranches()->head()) !== false) {
            $this->stdOut->writeln('<comment>You do not appear to be working on an issue branch.</comment>');
            return 1;
        }

        $branches = $workingCopy->getBranches()->all();
        $issue_version_branch = $this->getIssueVersionBranchName($issue);
        if (!in_array($issue_version_branch, $branches, true)) {
            $this->stdErr->writeln("Issue branch $issue_version_branch does not exist locally.");
            return 1;
        }

        // Create a diff from the first commit of the issue branch.
        $previousCommit = trim($workingCopy->run('rev-parse', ['@~1']));
        $diffOutput = $workingCopy->diff($previousCommit);
        $filename = $this->cwd . DIRECTORY_SEPARATOR . $this->buildInterdiffName($issue);
        file_put_contents($filename, $diffOutput);
        $this->stdOut->writeln("<comment>Interdiff written to {$filename}</comment>");

        $diffStat = $workingCopy->diff($previousCommit, ['stat' => true]);
        $this->stdOut->write($diffStat);
        return 0;
    }

    /**
     * Generates a file name for an interdiff.
     *
     * @param \mglaman\DrupalOrg\RawResponse $issue
     *   The issue raw response.
     *
     * @return string
     *   The name of the interdiff file.
     */
    protected function buildInterdiffName(RawResponse $issue)
    {
        $comment_count = $issue->get('comment_count');
        return sprintf('interdiff-%s-%s-%s.txt', $issue->get('nid'), $comment_count, $comment_count + 1);
    }

}
