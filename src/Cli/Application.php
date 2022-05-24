<?php

namespace mglaman\DrupalOrgCli;

use Composer\InstalledVersions;
use Symfony\Component\Console\Application as ParentApplication;

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

    /**
     * @return \Symfony\Component\Console\Command\Command[]
     */
    public function getCommands(): array
    {
        static $commands = [];
        if (count($commands) > 0) {
            return $commands;
        }

        $commands[] = new Command\CacheClear();
        $commands[] = new Command\Completion();
        $commands[] = new Command\DrupalCi\ListResults();
        $commands[] = new Command\DrupalCi\Watch();
        $commands[] = new Command\Issue\Link();
        $commands[] = new Command\Issue\Branch();
        $commands[] = new Command\Issue\Patch();
        $commands[] = new Command\Issue\Interdiff();
        $commands[] = new Command\Issue\Apply();
        $commands[] = new Command\Project\Link();
        $commands[] = new Command\Project\Kanban();
        $commands[] = new Command\Project\ProjectIssues();
        $commands[] = new Command\Project\Releases();
        $commands[] = new Command\Project\ReleaseNotes();
        $commands[] = new Command\TravisCi\ListBuilds();
        $commands[] = new Command\TravisCi\Watch();
        $commands[] = new Command\Maintainer\Issues();
        $commands[] = new Command\Maintainer\ReleaseNotes();
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
