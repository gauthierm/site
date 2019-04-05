<?php

/**
 * Web application module for handling ad tracking
 *
 * @package   Site
 * @copyright 2007-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @todo      Make sure kwid works for Microsoft adCenter
 */
class SiteAdModule extends SiteApplicationModule
{
    // {{{ class constants

    /**
     * Value was not loaded and the default value is used.
     */
    const AD_NETWORK_GOOGLE = 1;
    const AD_NETWORK_MICROSOFT = 3;

    // }}}
    // {{{ protected properties

    /**
     * The name of the inbound ad tracking URI parameter
     *
     * Defaults to 'ad'. Google Analytics users may want to change this to
     * 'utm_source' or 'utm_campaign'.
     *
     * @var string
     *
     * @see SiteAdModule::setInboundTrackingId()
     * @see SiteAdModule::cleanInboundTrackingUri()
     */
    protected $inbound_tracking_id = 'ad';

    /**
     * Whether or not to automaticlaly clean the inbound tracking id from URIs
     *
     * Defaults to false.
     *
     * @var boolean
     *
     * @see SiteAdModule::setAutocleanUri()
     */
    protected $autoclean_uri = false;

    /**
     * Whether or not to automaticlaly create Ad objects for unknown inbound
     * tracking ids
     *
     * Defaults to false.
     *
     * @var boolean
     *
     * @see SiteAdModule::setAutocreateAd()
     */
    protected $autocreate_ad = false;

    /**
     * Whether or not to save a cookie with the referring ad network.
     *
     * Defaults to false.
     *
     * @var boolean
     *
     * @see SiteAdModule::setTrackAdNetwork()
     */
    protected $track_ad_network = false;

    /**
     * Tracking get vars for various ad networks.
     *
     * @var array
     *
     * @see SiteAdModule::trackAdNetwork()
     */
    protected $ad_network_tracking_ids = array(
        'gclid' => self::AD_NETWORK_GOOGLE,
        'kwid' => self::AD_NETWORK_MICROSOFT
    );

    /**
     * Google analytics utm_sources used for various ad networks.
     *
     * This is a secondary value to check when looking up ad network, and
     * requires the ads to be properly tagged with Google Analytics utm_*
     * variables, including a utm_medium that exists within
     * {@link SiteAdModule::$ad_network_utm_mediums}.
     *
     * @var array
     *
     * @see SiteAdModule::trackAdNetwork()
     */
    protected $ad_network_utm_sources = array(
        'google' => self::AD_NETWORK_GOOGLE,
        'microsoft' => self::AD_NETWORK_MICROSOFT,
        'bing' => self::AD_NETWORK_MICROSOFT
    );

    /**
     * Google analytics utm_mediums valid for ad networks.
     *
     * For the secondary ad network check, all ads need to be tagged with a
     * Google Analytics utm_medium from this list.
     *
     * @see SiteAdModule::trackAdNetwork()
     */
    protected $ad_network_utm_mediums = array('cpc');

    // }}}
    // {{{ public function init()

    /**
     * Initializes this module
     *
     * Stores ad object in the session module and initializes ad database
     * reference.
     */
    public function init()
    {
        $this->initAd();

        // set database on ad in session if it exists
        $ad = $this->getAd();
        if ($ad !== null) {
            $db = $this->app->getModule('SiteDatabaseModule')->getConnection();
            $ad->setDatabase($db);
        }

        if ($this->track_ad_network) {
            $this->trackAdNetwork();
        }
    }

    // }}}
    // {{{ public function getAd()

    /**
     * Gets the tracked inbound ad for the current session
     *
     * @return SiteAd the tracked inbound ad for the current session or null
     *                 if there is no tracked inbound ad for the current
     *                 session.
     */
    public function getAd()
    {
        $session = $this->app->getModule('SiteSessionModule');
        return isset($session->ad) ? $session->ad : null;
    }

    // }}}
    // {{{ public function clearAd()

    /**
     * Clears the tracked inbound ad from the current session
     */
    public function clearAd()
    {
        $session = $this->app->getModule('SiteSessionModule');
        unset($session->ad);
    }

    // }}}
    // {{{ public function depends()

    /**
     * Gets the module features this module depends on
     *
     * The site ad module depends on the SiteSessionModule and
     * SiteDatabaseModule features.
     *
     * @return array an array of {@link SiteApplicationModuleDependency}
     *                        objects defining the features this module
     *                        depends on.
     */
    public function depends()
    {
        $depends = parent::depends();
        $depends[] = new SiteApplicationModuleDependency('SiteDatabaseModule');
        $depends[] = new SiteApplicationModuleDependency('SiteSessionModule');

        if ($this->app->hasModule('SiteCookieModule')) {
            $depends[] = new SiteApplicationModuleDependency(
                'SiteCookieModule'
            );
        }

        return $depends;
    }

    // }}}
    // {{{ public function cleanInboundTrackingUri()

    /**
     * Removes inbound ad tracking id from the URI and relocates to a clean
     * URI
     *
     * @param SiteAd $ad the inbound tracking ad to clean from the URI.
     */
    public function cleanInboundTrackingUri(SiteAd $ad)
    {
        // Site URI may not have been parsed yet if autocleaning is used. This
        // must be a full URI as a result.
        $uri = SiteWebApplication::cleanUriGetVar(
            $_SERVER['REQUEST_URI'],
            $this->inbound_tracking_id,
            $ad->shortname
        );

        // relocate to cleaned URI
        $this->app->relocate($uri);
    }

    // }}}
    // {{{ public function setInboundTrackingId()

    /**
     * Sets the name of the URI parameter used for tracking inbound ads
     *
     * @param string the name of the URI parameter used for tracking inbound
     *                ads. Example: 'utm_source'.
     */
    public function setInboundTrackingId($id)
    {
        $this->inbound_tracking_id = (string) $id;
    }

    // }}}
    // {{{ public function setAutocleanUri()

    /**
     * Sets whether or not inbound tracking ids should be automatically cleaned
     * from URIs
     *
     * @param boolean $clean optional. True if inbound tracking ids should be
     *                        automatically cleaned from URIs and false if they
     *                        should not. If set to true, the ad tracking id is
     *                        removed from the URI and a relocate is performed.
     */
    public function setAutocleanUri($clean = true)
    {
        $this->autoclean_uri = (bool) $clean;
    }

    // }}}
    // {{{ public function setAutocreateAd()

    /**
     * Sets whether or not inbound ad tracking ids should automatically create
     * Ad objects in the database
     *
     * @param boolean $create optional. True if unknown inbound ad tracking
     *                         ids should automatically create an Ad object
     *                         in the database and false if they should not.
     */
    public function setAutocreateAd($create = true)
    {
        $this->autocreate_ad = (bool) $create;
    }

    // }}}
    // {{{ public function setTrackAdNetwork()

    /**
     * Sets whether or not to track referring ad network
     *
     * If the application has SiteCookieModule, the Ad Network is saved to a 30
     * day cookie. Otherwise its saved in the session.
     *
     * @param boolean $track optional. True if ad network should be tracked and
     *                        false if they should not.
     */
    public function setTrackAdNetwork($track = true)
    {
        $this->track_ad_network = (bool) $track;
    }

    // }}}
    // {{{ public function getAdNetwork()

    /**
     * Returns any saved Ad Network
     *
     * @return string the shortname of the ad network.
     */
    public function getAdNetwork()
    {
        $ad_network = null;

        if ($this->track_ad_network === true) {
            // Don't combine the cookie module check, and then the check to see
            // if the cookie isset into one statement, as we don't want to fall
            // back onto the session if the cookie module does exist.
            if ($this->app->hasModule('SiteCookieModule')) {
                $cookie_module = $this->app->getModule('SiteCookieModule');
                if (isset($cookie_module->ad_network)) {
                    $ad_network = $cookie_module->ad_network;
                }
            } elseif (
                $this->app->getModule('SiteSessionModule')->isActive() &&
                isset($this->app->getModule('SiteSessionModule')->ad_network)
            ) {
                $ad_network = $this->app->getModule('SiteSessionModule')
                    ->ad_network;
            }
        }

        return $ad_network;
    }

    // }}}
    // {{{ protected function initAd()

    /**
     * Reads inbound tracking ids from the request URI and saves a new ad
     * referrer
     *
     * If autoclean URI is specified, after the referral is logged, the inbound
     * tracking id is removed from the URI through a relocate.
     */
    protected function initAd()
    {
        $db = $this->app->getModule('SiteDatabaseModule')->getConnection();

        $shortname = SiteApplication::initVar(
            $this->inbound_tracking_id,
            null,
            SiteApplication::VAR_POST | SiteApplication::VAR_GET
        );

        if ($shortname !== null) {
            $class_name = SwatDBClassMap::get('SiteAd');
            $ad = new $class_name();
            $ad->setDatabase($db);
            $loaded = $ad->loadFromShortname($shortname);

            // autocreate ad if ad does not exist and autocreate is on
            if (!$loaded && $this->autocreate_ad) {
                $ad = $this->createAd($shortname);
                $ad->setDatabase($db);
                $ad->save();
                $loaded = true;
            }

            if ($loaded) {
                $session = $this->app->getModule('SiteSessionModule');
                if (!$session->isActive()) {
                    $session->activate();
                }

                $session->ad = $ad;

                /*
                 * Due to mass mailings, large numbers of people follow links
                 * with ads which can lead to database deadlock when inserting
                 * the referrer. Here we make five attempts before giving up
                 * and throwing the exception.
                 */
                if ($this->app->config->ads->save_referer) {
                    for ($attempt = 0; true; $attempt++) {
                        try {
                            $this->insertInboundTrackingReferrer($ad);
                            break;
                        } catch (SwatDBException $e) {
                            if ($attempt > 5) {
                                throw $e;
                            }
                        }
                    }
                }

                if ($this->autoclean_uri) {
                    $this->cleanInboundTrackingUri($ad);
                }
            }
        }
    }

    // }}}
    // {{{ protected function insertInboundTrackingReferrer()

    /**
     * Inserts a referrer for an inbound tracking id
     *
     * This is used to cout the number of site referrals for a particular
     * tracking id.
     *
     * @param SiteAd $ad the inbound ad to track.
     */
    protected function insertInboundTrackingReferrer(SiteAd $ad)
    {
        $db = $this->app->getModule('SiteDatabaseModule')->getConnection();

        $class_name = SwatDBClassMap::get('SiteAdReferrer');
        $ad_referrer = new $class_name();

        $ad_referrer->setDatabase($db);
        $ad_referrer->createdate = new SwatDate();
        $ad_referrer->createdate->toUTC();
        $ad_referrer->http_referer = SiteApplication::initVar(
            'HTTP_REFERER',
            null,
            SiteApplication::VAR_SERVER
        );

        // truncate HTTP referrer to 255 characters
        if ($ad_referrer->http_referer !== null) {
            $ad_referrer->http_referer = mb_substr(
                $ad_referrer->http_referer,
                0,
                255
            );
        }

        $ad_referrer->ad = $ad;

        $ad_referrer->save();
    }

    // }}}
    // {{{ protected function createAd()

    /**
     * Creates a new ad object with the given inbound tracking id
     *
     * @param string $shortname the inbound tracking id of the Ad.
     *
     * @return SiteAd the newly created ad object. The Ad is not yet saved in
     *                 the database.
     */
    protected function createAd($shortname)
    {
        $class_name = SwatDBClassMap::get('SiteAd');
        $ad = new $class_name();

        $ad->title = (string) $shortname;
        $ad->shortname = (string) $shortname;
        $ad->createdate = new SwatDate();
        $ad->createdate->toUTC();

        return $ad;
    }

    // }}}
    // {{{ protected function trackAdNetwork()

    /**
     * Saves the ad network for later use.
     *
     * Saves the most recent ad network, just as Google Analytics only saves the
     * most recent ad when tracking users.
     */
    protected function trackAdNetwork()
    {
        $ad_network = null;

        // primary check for ad network based on tracking id
        foreach ($this->ad_network_tracking_ids as $id => $network) {
            $value = SiteApplication::initVar(
                $id,
                null,
                SiteApplication::VAR_GET
            );

            // if we find one of the tracking ids in the query string, don't
            // worry about checking the rest, an ad will only belong to one
            // network.
            if ($value !== null) {
                $ad_network = $network;
                break;
            }
        }

        // if tracking id didn't find anything, check for Google Analytic
        // tracking vars that signify an ad network.
        if ($ad_network === null) {
            $utm_medium = mb_strtolower(
                SiteApplication::initVar(
                    'utm_medium',
                    null,
                    SiteApplication::VAR_GET
                )
            );

            if (in_array($utm_medium, $this->ad_network_utm_mediums)) {
                $utm_source = mb_strtolower(
                    SiteApplication::initVar(
                        'utm_source',
                        null,
                        SiteApplication::VAR_GET
                    )
                );

                if (
                    array_key_exists($utm_source, $this->ad_network_utm_sources)
                ) {
                    $ad_network = $this->ad_network_utm_sources[$utm_source];
                }
            }
        }

        if ($ad_network !== null) {
            if ($this->app->hasModule('SiteCookieModule')) {
                $cookie_module = $this->app->getModule('SiteCookieModule');
                $cookie_module->setCookie(
                    'ad_network',
                    $ad_network,
                    strtotime('+30 days')
                );
            } else {
                $session = $this->app->getModule('SiteSessionModule');
                if (!$session->isActive()) {
                    $session->activate();
                }
                $session->ad_network = $ad_network;
            }
        }
    }

    // }}}
}
