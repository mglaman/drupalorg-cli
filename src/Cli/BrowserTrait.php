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
    protected function getDefaultBrowser(): ?string
    {
        $potential = array('xdg-open', 'open', 'start');
        foreach ($potential as $browser) {
            $exitCode = (int) trim(shell_exec("command -v $browser; echo $?"));
            if ($exitCode === 0) {
                return $browser;
            }
        }
        return null;
    }

    /**
     * Open a URL in the browser, or print it.
     *
     * @param $url
     * @param \Symfony\Component\Console\Output\OutputInterface $stdErr
     * @param \Symfony\Component\Console\Output\OutputInterface $stdOut
     */
    protected function openUrl(
        $url,
        OutputInterface $stdErr,
        OutputInterface $stdOut
    ): int {
        $browser = $this->getDefaultBrowser();
        if ($browser !== null) {
            $opened = (int) trim(shell_exec("$browser $url 2>&1; echo $?"));
            if ($opened === 0) {
                $stdErr->writeln("<info>Opened</info>: $url");
                return 0;
            }
        } else {
            $stdErr->writeln('<error>Browser command not found</error>');
            return 1;
        }
        $stdOut->writeln($url);
        return 0;
    }
}
