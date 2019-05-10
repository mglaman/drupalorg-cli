<?php

namespace mglaman\DrupalOrgCli;

use Symfony\Component\Console\Application as ParentApplication;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Application extends ParentApplication
{
    const VERSION = '0.0.9';
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct('Drupal.org CLI', self::VERSION);
        $this->setDefaultTimezone();

        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../'));
        $loader->load('services.yaml');

        $container->addCompilerPass(new AddConsoleCommandPass());
        $container->compile();

        foreach ($container->getParameter('console.command.ids') as $id) {
            $this->add($container->get($id));
        }

    }

    /**
     * @inheritdoc
     */
    protected function getDefaultCommands()
    {
        return array(new HelpCommand(), new Command\ListCommand());
    }

    /**
     * Set the default timezone.
     *
     * PHP 5.4 has removed the autodetection of the system timezone,
     * so it needs to be done manually.
     * UTC is the fallback in case autodetection fails.
     */
    protected function setDefaultTimezone()
    {
        $timezone = 'UTC';
        if (is_link('/etc/localtime')) {
            // Mac OS X (and older Linuxes)
            // /etc/localtime is a symlink to the timezone in /usr/share/zoneinfo.
            $filename = readlink('/etc/localtime');
            if (strpos($filename, '/usr/share/zoneinfo/') === 0) {
                $timezone = substr($filename, 20);
            }
        } elseif (file_exists('/etc/timezone')) {
            // Ubuntu / Debian.
            $data = file_get_contents('/etc/timezone');
            if ($data) {
                $timezone = trim($data);
            }
        } elseif (file_exists('/etc/sysconfig/clock')) {
            // RHEL/CentOS
            $data = parse_ini_file('/etc/sysconfig/clock');
            if (!empty($data['ZONE'])) {
                $timezone = trim($data['ZONE']);
            }
        }
        date_default_timezone_set($timezone);
    }
}
