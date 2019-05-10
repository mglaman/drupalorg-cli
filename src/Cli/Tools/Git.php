<?php declare(strict_types=1);

namespace mglaman\DrupalOrgCli;

use GitWrapper\GitWorkingCopy;
use GitWrapper\GitWrapper;

final class Git
{
    private $git;

    public function __construct()
    {
        $this->git = new GitWrapper();
        $this->git->setTimeout(120);
        $version = trim(substr($this->git->version(), 12));
        if (version_compare($version, '2.0.0', '<')) {
            throw new \RuntimeException('Use Git > 2.0.0.');
        }
    }

    public function cloneRepository(string $repository, string $directory, ?string $branch = null): GitWorkingCopy
    {
        $options = [];
        if ($branch !== null) {
            $options['branch'] = $branch;
        }
        return $this->git->cloneRepository($repository, $directory, $options);
    }

    public function getWorkingCopy(string $directory): ?GitWorkingCopy
    {
        $workingCopy = $this->git->workingCopy($directory);
        return $workingCopy->isCloned() ? $workingCopy : null;
    }

    public function getIssueNidFromBranch(GitWorkingCopy $workingCopy): ?string
    {
        $branch = $workingCopy->getBranches()->head();
        $issueCheckMatches = [];
        preg_match('/[.]*([\d]+)[.]*/', $branch, $issueCheckMatches);
        return reset($issueCheckMatches) ?: null;
    }
}
