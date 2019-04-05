<?php

/**
 * Abstract base class for a search indexer applications
 *
 * @package   Site
 * @copyright 2006-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteSearchIndexer extends SiteCommandLineApplication
{
    // {{{ public properties

    /**
     * A convenience reference to the database object
     *
     * @var MDB2_Driver
     */
    public $db;

    // }}}
    // {{{ protected function getDefaultModuleList()

    /**
     * Gets the list of modules to load for this search indexer
     *
     * @return array the list of modules to load for this application.
     *
     * @see SiteApplication::getDefaultModuleList()
     */
    protected function getDefaultModuleList()
    {
        return array_merge(parent::getDefaultModuleList(), [
            'config' => SiteCommandLineConfigModule::class,
            'database' => SiteDatabaseModule::class
        ]);
    }

    // }}}
    // {{{ protected function configure()

    /**
     * Configures modules of this application before they are initialized
     *
     * @param SiteConfigModule $config the config module of this application to
     *                                  use for configuration other modules.
     */
    protected function configure(SiteConfigModule $config)
    {
        parent::configure($config);
        $this->database->dsn = $config->database->dsn;
    }

    // }}}
}
