<?php declare(strict_types=1);

namespace mglaman\DrupalOrgCli;

use Symfony\Component\Console\Input\InputInterface;

trait IssueNidArgumentTrait
{
    protected function getNidArgument(InputInterface $input): ?string
    {
        $nid = $input->getArgument('nid');
        if ($nid === null) {
            $git = new Git();
            $workingCopy = $git->getWorkingCopy(getcwd());
            if ($workingCopy === null) {
                return null;
            }
            $nid = $git->getIssueNidFromBranch($workingCopy);
        }
        return $nid;
    }
}
