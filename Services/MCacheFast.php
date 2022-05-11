<?php

namespace Orkester\Services;

use Orkester\Manager;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Helper\Psr16Adapter;

class MCacheFast
{
    private Psr16Adapter $cache;

    public function __construct(string $driver = 'apcu')
    {
        $path = Manager::getConf('cache.path');
        // Setup File Path on your config files
        CacheManager::setDefaultConfig(new ConfigurationOption([
            'path' => $path
        ]));
        $this->cache = new Psr16Adapter('apcu');
    }

    public function getCache(): Psr16Adapter
    {
        return $this->cache;
    }

}

