<?php declare(strict_types=1);

namespace mglaman\DrupalOrgCli\Command\Project;

use mglaman\DrupalOrgCli\Command\Command;
use mglaman\DrupalOrgCli\Git;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CloneProject extends Command
{

    /**
     * @var \mglaman\DrupalOrgCli\Git
     */
    private $git;

    public function __construct(Git $git)
    {
        parent::__construct();
        $this->git = $git;
    }

    protected function configure()
    {
        $this->setName('project:clone')
          ->setAliases(['clone'])
          ->addArgument('project', InputArgument::REQUIRED, 'The project machine name')
          ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Specific branch')
          ->addOption('maintainer', 'm', InputOption::VALUE_NONE, 'Use maintainer Git url')
          ->addOption('destination', 'd', InputOption::VALUE_REQUIRED, 'Destination')
          ->setDescription('Clones a project');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectName = $this->stdIn->getArgument('project');
        if ($input->getOption('maintainer') === true) {
            $gitUrl = "git@git.drupal.org:project/$projectName.git";
        } else {
            $gitUrl = "https://git.drupalcode.org/project/$projectName.git";
        }
        $directory = $this->stdIn->getOption('destination');
        if ($directory === null) {
            $directory = getcwd() . DIRECTORY_SEPARATOR . $projectName;
        }
        if (is_dir($directory)) {
            $this->stdOut->writeln("$directory already exists and is not an empty directory.");
            return 1;
        }
        $this->git->cloneRepository($gitUrl, $directory, $this->stdIn->getOption('branch'));
        $this->stdOut->write("<info>$projectName</info> cloned to <info>$directory</info>");
        return 0;
    }

}
