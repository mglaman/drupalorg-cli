<?php

namespace mglaman\DrupalOrgCli\Command;

use mglaman\DrupalOrgCli\Cache;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CacheClear extends Command
{
    protected function configure()
    {
        $this
          ->setName('cache:clear')
          ->setAliases(['cc'])
          ->setDescription('Clears caches');
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Cache::getCache()->flushAll();
    }
}
