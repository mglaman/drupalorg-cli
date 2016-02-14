<?php

namespace mglaman\DrupalOrgCli;

use Symfony\Component\Console\Output\OutputInterface;

trait BrowserTrait
{
    /**
     * Find a default browser to use.
     *
     * @return string|false
     */
    protected function getDefaultBrowser()
    {
        $potential = array('xdg-open', 'open', 'start');
        foreach ($potential as $browser) {
            // Check if command exists by executing help flag.

            if (shell_exec("command -v $browser; echo $?") == 0) {
                return $browser;
            }
        }
        return false;
    }

    /**
     * Open a URL in the browser, or print it.
     *
     * @param $url
     * @param \Symfony\Component\Console\Output\OutputInterface $stdErr
     * @param \Symfony\Component\Console\Output\OutputInterface $stdOut
     */
    protected function openUrl($url, OutputInterface $stdErr, OutputInterface $stdOut)
    {
        $browser = $this->getDefaultBrowser();
        if ($browser) {
            $opened = shell_exec("$browser $url 2>&1; echo $?");
            if ($opened == 0) {
                $stdErr->writeln("<info>Opened</info>: $url");
                return;
            }
        } else {
            $stdErr->writeln("<error>Browser not found: $browser</error>");
        }
        $stdOut->writeln($url);
    }
}