<?php

/**
 * Web application module for cookies
 *
 * @package   Site
 * @copyright 2006-2016 silverorange
 * @todo      For sites that use multiple instances on their own subdomains set
 *            the subdomain on the cookies to prevent all cookies from existing
 *            on the parent domain. Also useful would be setting correct path
 *            for admin's that share a common base domain.
 */
class SiteCookieModule extends SiteApplicationModule
{
    // {{{ private properties

    private $salt = '';

    // }}}
    // {{{ public function init()

    /**
     * Initializes this cookie module
     *
     * No initilization tasks are performed for the cookie module.
     */
    public function init()
    {
    }

    // }}}
    // {{{ public function setSalt()

    /**
     * Set salt
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;
    }

    // }}}
    // {{{ public function setCookie()

    /**
     * Sets a cookie
     *
     * @param string $name the name of the cookie to set.
     * @param mixed $value the value of the cookie.
     * @param mixed $expiry the expiry date as a UNIX timestamp or a
     *                       string parsable by strtotime().
     * @param string $path the URL path this cookie is valid for.
     * @param string $domain the domain this cookie is valid for.
     */
    public function setCookie(
        $name,
        $value,
        $expiry = null,
        $path = '/',
        $domain = null
    ) {
        $name = $this->getPrefix() . '_' . $name;

        if ($expiry === null) {
            $expiry = strtotime('+90 days');
        } elseif (is_string($expiry)) {
            $expiry = strtotime($expiry);
        }

        $cookie_value = SwatString::signedSerialize($value, $this->salt);

        // TODO: get subdomain from application when on a multi-instance site.
        // if ($domain = null)
        //   $domain =

        setcookie($name, $cookie_value, $expiry, $path);
        // setcookie($name, $cookie_value, $expiry, $path, $domain);
    }

    // }}}
    // {{{ public function removeCookie()

    /**
     * Remove a cookie
     *
     * @param string $name the name of the cookie to set.
     * @param string $path the URL path this cookie is valid for.
     * @param string $domain the domain this cookie is valid for.
     */
    public function removeCookie($name, $path = '/', $domain = null)
    {
        $name = $this->getPrefix() . '_' . $name;

        // Set expiry time to the past. The expiry of 25 hours in the past is
        // used because time() uses the server's local time and some browsers
        // use local time rather than UTC to trigger cookie deletion. 25 hours
        // takes into account all time-zone differences.
        $expiry = time() - 3600 * 25;

        // Some browsers set the cookie value to 'deleted' when an empty string
        // is used as a cookie value. The value '0' is chosen instead for
        // unsetting cookies.
        $value = 0;

        // TODO: get from application when on a multi-instance site.
        // if ($domain = null)
        //   $domain =

        setcookie($name, $value, $expiry, $path);
        // setcookie($name, $value, $expiry, $path, $domain);

        unset($_COOKIE[$name]);
    }

    // }}}
    // {{{ public function depends()

    /**
     * Gets the module features this module depends on
     *
     * @return array an array of {@link SiteModuleDependency} objects defining
     *                        the features this module depends on.
     */
    public function depends()
    {
        $depends = parent::depends();

        if ($this->app->hasModule('SiteMultipleInstanceModule')) {
            $depends[] = new SiteApplicationModuleDependency(
                'SiteMultipleInstanceModule'
            );
        }

        return $depends;
    }

    // }}}
    // {{{ public function __get()

    /**
     * Gets a cookie value
     *
     * @param string $name the name of the cookie to get.
     *
     * @return mixed the value of the cookie. If there is an error
     *               unserializing the cookie value, null is returned.
     */
    public function __get($name)
    {
        $name = $this->getPrefix() . '_' . $name;

        if (!isset($_COOKIE[$name])) {
            throw new SiteCookieException("Cookie '{$name}' is not set.");
        }

        try {
            $value = SwatString::signedUnserialize(
                $_COOKIE[$name],
                $this->salt
            );
        } catch (SwatInvalidSerializedDataException $e) {
            // Ignore common cookie values used to remove cookies.
            $ignored_values = array(0, '');

            if (!in_array($_COOKIE[$name], $ignored_values)) {
                // If the cookie can't be unserialized, then log it and
                // continue execution.
                $e = new SiteCookieException($e);
                $e->process(false);
            }

            // Remove the cookie to prevent further exceptions.
            $value = null;
            $this->removeCookie($name);
        }

        return $value;
    }

    // }}}
    // {{{ public function __isset()

    /**
     * Checks the existence of a cookie
     *
     * @param string $name the name of the cookie to check.
     */
    public function __isset($name)
    {
        $name = $this->getPrefix() . '_' . $name;
        return isset($_COOKIE[$name]);
    }

    // }}}
    // {{{ protected function getPrefix()

    /**
     * Gets the prefix for the cookie name
     *
     * @return string Cookie prefix
     */
    protected function getPrefix()
    {
        $prefix = $this->app->id;

        if ($this->app->hasModule('SiteMultipleInstanceModule')) {
            $instance = $this->app->getModule('SiteMultipleInstanceModule');
            if ($instance->getInstance() !== null) {
                $prefix = $instance->getInstance()->shortname;
            }
        }

        return $prefix;
    }

    // }}}
    // {{{ private function getHash()

    /**
     * Gets the hash value for a cookie value
     *
     * @deprecated This method only exists for backwards compatibility with
     *             the old, less secure data-signing technique. It will be
     *             removed in future versions of SiteCookieModule.
     */
    private function getHash($value)
    {
        return md5($this->salt . serialize($value));
    }

    // }}}
}
