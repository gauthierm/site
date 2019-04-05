<?php

/**
 * Builds an object to display HTML head entries for an application using
 * the Concentrate library
 *
 * @package   Site
 * @copyright 2010-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteHtmlHeadEntrySetDisplayerFactory
{
    // {{{ protected properties

    /**
     * Indexed by application id
     *
     * @var array
     */
    protected $displayers = array();

    /**
     * @var string
     *
     * @see SiteHtmlHeadEntrySetDisplayerFactory::setDisplayerClass()
     */
    protected $displayer_class = 'SwatHtmlHeadEntrySetDisplayer';

    // }}}
    // {{{ public function build()

    public function build(SiteApplication $app)
    {
        if (!isset($this->displayers[$app->id])) {
            $resources = $app->config->resources;
            $memcache = $app->config->memcache;

            // build cache hierarchy
            $cache = new Concentrate_CacheArray();

            $apc_cache = new Concentrate_CacheAPC($memcache->app_ns);
            $cache->setSubcache($apc_cache);

            if ($memcache->resource_cache) {
                $memcached = new Memcached();
                $memcached->addServer($memcache->server, 11211);
                $memcache_cache = new Concentrate_CacheMemcache(
                    $memcached,
                    $memcache->app_ns
                );

                $apc_cache->setSubcache($memcache_cache);
            }

            // build data provider
            $data_provider = new Concentrate_DataProvider(array(
                'stat' => $memcache->resource_cache_stat
            ));

            // build concentrator
            $concentrator = new Concentrate_Concentrator(array(
                'cache' => $cache,
                'data_provider' => $data_provider
            ));

            // load data files
            $finder = new SiteConcentrateFileFinder();
            $concentrator->loadDataFiles($finder->getDataFiles());

            $class_name = $this->displayer_class;
            $this->displayers[$app->id] = new $class_name($concentrator);
        }

        return $this->displayers[$app->id];
    }

    // }}}
    // {{{ public function setDisplayerClass()

    public function setDisplayerClass($class_name)
    {
        $this->displayer_class = $class_name;
    }

    // }}}
    // {{{ public function getDisplayerClass()

    public function getDisplayerClass()
    {
        return $this->displayer_class;
    }

    // }}}
}
