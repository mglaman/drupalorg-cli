<?php

namespace mglaman\DrupalOrgCli;

use Composer\InstalledVersions;
use Symfony\Component\Console\Application as ParentApplication;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class Application extends ParentApplication
{

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        try {
            $version = InstalledVersions::getPrettyVersion('mglaman/drupalorg-cli');
        } catch (\OutOfBoundsException $e) {
            $version = '0.0.0';
        }
        parent::__construct('Drupal.org CLI', $version);
        $this->setDefaultTimezone();
        $this->addCommands($this->getCommands());
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOption(new InputOption(
            'no-cache',
            null,
            InputOption::VALUE_NONE,
            'Bypass Drupal.org HTTP caching and fetch a fresh response.'
        ));
        return $definition;
    }

    /**
     * @return \Symfony\Component\Console\Command\Command[]
     */
    public function getCommands(): array
    {
        static $commands = [];
        if (count($commands) > 0) {
            return $commands;
        }

        $commands[] = new \SelfUpdate\SelfUpdateCommand(
            new \SelfUpdate\SelfUpdateManager(
                $this->getName(),
                $this->getVersion(),
                'mglaman/drupalorg-cli'
            )
        );
        $commands[] = new Command\Completion();
        $commands[] = new Command\Issue\Link();
        $commands[] = new Command\Issue\Branch();
        $commands[] = new Command\Issue\Patch();
        $commands[] = new Command\Issue\Interdiff();
        $commands[] = new Command\Issue\Apply();
        $commands[] = new Command\Issue\Show();
        $commands[] = new Command\Project\Link();
        $commands[] = new Command\Project\Kanban();
        $commands[] = new Command\Project\ProjectIssues();
        $commands[] = new Command\Issue\Search();
        $commands[] = new Command\Project\Releases();
        $commands[] = new Command\Project\ReleaseNotes();
        $commands[] = new Command\Maintainer\Issues();
        $commands[] = new Command\Maintainer\ReleaseNotes();
        $commands[] = new Command\Skill\Install();
        $commands[] = new Command\Issue\GetFork();
        $commands[] = new Command\Issue\SetupRemote();
        $commands[] = new Command\Issue\Checkout();
        $commands[] = new Command\MergeRequest\ListMergeRequests();
        $commands[] = new Command\MergeRequest\GetDiff();
        $commands[] = new Command\MergeRequest\GetFiles();
        $commands[] = new Command\MergeRequest\GetStatus();
        $commands[] = new Command\MergeRequest\GetLogs();
        $commands[] = new Command\Mcp\Serve();
        $commands[] = new Command\Mcp\Config();
        return $commands;
    }

    /**
     * Set the default timezone.
     *
     * PHP 5.4 has removed the autodetection of the system timezone,
     * so it needs to be done manually.
     * UTC is the fallback in case autodetection fails.
     */
    protected function setDefaultTimezone(): void
    {
        $timezone = 'UTC';
        if (is_link('/etc/localtime')) {
            // Mac OS X (and older Linuxes)
            // /etc/localtime is a symlink to the timezone in /usr/share/zoneinfo.
            $filename = readlink('/etc/localtime');
            if ($filename !== false && strpos(
                $filename,
                '/usr/share/zoneinfo/'
            ) === 0) {
                $timezone = substr($filename, 20);
            }
        } elseif (file_exists('/etc/timezone')) {
            // Ubuntu / Debian.
            $data = file_get_contents('/etc/timezone');
            if ($data !== false && $data !== '') {
                $timezone = trim($data);
            }
        } elseif (file_exists('/etc/sysconfig/clock')) {
            // RHEL/CentOS
            $data = parse_ini_file('/etc/sysconfig/clock');
            if ($data !== false && isset($data['ZONE'])) {
                $timezone = trim($data['ZONE']);
            }
        }
        date_default_timezone_set($timezone);
    }
}
