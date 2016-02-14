<?php

namespace mglaman\DrupalOrgCli;

use Doctrine\Common\Cache\FilesystemCache;

class Cache
{
    protected static $cacheDir;
    protected static $cache;


    /**
     * @param string $cacheDir
     */
    public static function setCacheDir($cacheDir)
    {
        self::$cacheDir = $cacheDir;
    }

    /**
     * @return \Doctrine\Common\Cache\CacheProvider
     */
    public static function getCache()
    {
        if (!isset(self::$cache)) {
            self::$cache = new FilesystemCache(self::$cacheDir, FilesystemCache::EXTENSION, 0077);
        }
        return self::$cache;
    }
}