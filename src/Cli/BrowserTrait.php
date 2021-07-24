<?php

namespace mglaman\DrupalOrgCli;

use Symfony\Component\Console\Output\OutputInterface;

trait BrowserTrait
{

    protected function getDefaultBrowser(): ?string
    {
        $potential = array('xdg-open', 'open', 'start');
        foreach ($potential as $browser) {
            // Check if command exists by executing help flag.
            if (shell_exec("command -v $browser; echo $?") === 0) {
                return $browser;
            }
        }
        return null;
    }

    protected function openUrl(string $url, OutputInterface $stdErr, OutputInterface $stdOut): void
    {
        $browser = $this->getDefaultBrowser();
        if ($browser !== null) {
            $opened = shell_exec("$browser $url 2>&1; echo $?");
            if ($opened === 0) {
                $stdErr->writeln("<info>Opened</info>: $url");
                return;
            }
        } else {
            $stdErr->writeln("<error>Browser not found: $browser</error>");
        }
        $stdOut->writeln($url);
    }
}
