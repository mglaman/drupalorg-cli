<?php

namespace mglaman\DrupalOrgCli;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

class Cache
{

    protected static FilesystemAdapter $cache;

    public static function getCache(): FilesystemAdapter
    {
        if (!isset(self::$cache)) {
            $tmpPath = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'drupalorg-cli-cache';
            if (!is_dir($tmpPath) && !mkdir($tmpPath) && !is_dir($tmpPath)) {
                throw new \RuntimeException(
                    sprintf('Directory "%s" was not created', $tmpPath)
                );
            }
            self::$cache = new FilesystemAdapter(
                '',
                0,
                $tmpPath,
                // Skip igbinary to avoid deprecations from Guzzle cache
                // middleware and caching streams.
                new DefaultMarshaller(false, true)
            );
        }
        return self::$cache;
    }
}
