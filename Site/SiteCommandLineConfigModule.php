<?php

/**
 * Common configuration module for command-line applications
 *
 * Contains a common configuration for command-line applications. This config
 * module is optional, but can be used to aid rapid development of command-line
 * apps.
 *
 * This module expects an application that provides:
 * - a database module
 *
 * This module configures:
 * - error and exception logging
 * - application database connection
 * - application time zone
 * - application locale
 *
 * @package   Site
 * @copyright 2007-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteCommandLineConfigModule extends SiteConfigModule
{
    // {{{ protected function configure()

    /**
     * Configures modules of the application before they are initialized
     */
    public function configure()
    {
        parent::configure();

        $this->app->database->dsn = $this->database->dsn;

        if ($this->date->time_zone !== null) {
            $this->app->default_time_zone = new DateTimeZone(
                $this->date->time_zone
            );
        }

        $this->app->default_locale = $this->i18n->locale;

        setlocale(LC_ALL, $this->i18n->locale . '.UTF-8');
    }

    // }}}
}
