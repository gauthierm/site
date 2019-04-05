<?php

/**
 * @package   Site
 * @copyright 2017 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteXMLSiteMapTemplate extends SiteAbstractTemplate
{
    // {{{ public function display()

    public function display(SiteLayoutData $data)
    {
        // Set content type to XML
        header('Content-type: text/xml; charset=UTF-8');

        // Disable any caching with HTTP headers
        // Any date in the past will do here
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

        // Set always modified
        // for HTTP/1.1
        header('Cache-Control: no-cache, must-revalidate max-age=0');
        // for HTTP/1.0
        header('Pragma: no-cache');

        echo '<?xml version="1.0" encoding="UTF-8"?>';

        echo $data->site_map;
    }

    // }}}
}
