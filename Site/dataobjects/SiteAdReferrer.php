<?php

/**
 * Tracked inbound ad referrer
 *
 * @package   Site
 * @copyright 2007-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       SiteAd
 */
class SiteAdReferrer extends SwatDBDataObject
{
    // {{{ public properties

    /**
     * Unique identifier for this ad referrer
     *
     * @var integer
     */
    public $id;

    /**
     * The date this referral occurred
     *
     * @var SwatDate
     */
    public $createdate;

    /**
     * The HTTP referer of this referral
     *
     * May not exist if there was no HTTP referer.
     *
     * @var string
     */
    public $http_referer;

    // }}}
    // {{{ protected function init()

    protected function init()
    {
        $this->table = 'AdReferrer';
        $this->id_field = 'integer:id';
        $this->registerDateProperty('createdate');
        $this->registerInternalProperty('ad', 'SiteAd');
    }

    // }}}
}
