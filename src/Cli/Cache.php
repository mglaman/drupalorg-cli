<?php

namespace mglaman\DrupalOrgCli;

use Doctrine\Common\Cache\FilesystemCache;

class Cache
{

    protected static $cache;

    /**
     * @return \Doctrine\Common\Cache\CacheProvider
     */
    public static function getCache()
    {
        if (!isset(self::$cache)) {
            $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '/drupalorg-cli-cache';
            @mkdir($tmpPath);
            self::$cache = new FilesystemCache($tmpPath, FilesystemCache::EXTENSION, 0077);
        }
        return self::$cache;
    }
}
