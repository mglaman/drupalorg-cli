<?php

namespace mglaman\DrupalOrgCli;

use Doctrine\Common\Cache\FilesystemCache;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Cache
{

    protected static FilesystemAdapter $cache;

    public static function getCache(): FilesystemAdapter
    {
        if (!isset(self::$cache)) {
            $tmpPath = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'drupalorg-cli-cache';
            if (!is_dir($tmpPath) && !mkdir($tmpPath) && !is_dir($tmpPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $tmpPath));
            }
            self::$cache = new FilesystemAdapter(
              '',
              0,
                $tmpPath
            );
        }
        return self::$cache;
    }
}
