<?php

namespace knav\Cache;

class Manager extends \VuFind\Cache\Manager
{

    /**
     * Constructor
     *
     * @param Config $config       Main VuFind configuration
     * @param Config $searchConfig Search configuration
     */
    public function __construct($config, $searchConfig)
    {
        $cacheConfig = isset($config->Cache) ? $config->Cache : false;
        $this->defaults = $cacheConfig ? $cacheConfig->toArray() : false;

        $cacheBase = $this->getCacheDir();

        // Set up standard file-based caches:
        foreach (['config', 'cover', 'language', 'object'] as $cache) {
            $this->createFileCache($cache, $cacheBase . $cache . 's');
        }

        $searchCacheType = isset($searchConfig->Cache->type)
            ? $searchConfig->Cache->type : false;
        switch ($searchCacheType) {
        case 'APC':
            $this->createAPCCache('searchspecs');
            break;
        case 'File':
            $this->createFileCache(
                'searchspecs', $cacheBase . 'searchspecs'
            );
            break;
        case false:
            $this->createNoCache('searchspecs');
            break;
        }
    }

}

