<?php

/**
 * A generated XML Site Map Index
 *
 * @package   Site
 * @copyright 2009-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       http://www.sitemaps.org/
 */
abstract class SiteXmlSiteMapIndexPage extends SitePageDecorator
{
    // {{{ public function __construct()

    public function __construct(SiteAbstractPage $page)
    {
        parent::__construct($page);
        $this->setLayout(new SiteXmlSiteMapLayout($this->app));
    }

    // }}}
    // {{{ public function build()

    public function build()
    {
        $this->layout->startCapture('site_map');

        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $this->displaySiteMapIndex();
        echo '</sitemapindex>';

        $this->layout->endCapture();
    }

    // }}}
    // {{{ protected function displayIndex()

    protected function displayIndex($path, SwatDate $last_modified = null)
    {
        echo "<sitemap>\n";

        printf(
            "<loc>%s</loc>\n",
            htmlspecialchars($this->app->getBaseHref() . $path)
        );

        if ($last_modified !== null) {
            printf("<lastmod>%s</lastmod>\n", $last_modified->getISO8601());
        }

        echo "</sitemap>\n";
    }

    // }}}
    // {{{ abstract protected function displaySiteMapIndex()

    abstract protected function displaySiteMapIndex();

    // }}}
}
