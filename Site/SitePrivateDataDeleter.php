<?php

/**
 * Abstract base for a class that deletes private data
 *
 * @package   Site
 * @copyright 2006-2016 silverorange
 */
abstract class SitePrivateDataDeleter extends SwatObject
{
    // {{{ public properties

    /**
     * A reference to the application
     *
     * @var SitePrivateDataDeleterApplication
     */
    public $app;

    // }}}
    // {{{ abstract public function run()

    abstract public function run();

    // }}}
}
