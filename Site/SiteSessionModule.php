<?php

/**
 * Web application module for sessions
 *
 * @package   Site
 * @copyright 2006-2017 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteSessionModule extends SiteApplicationModule
{
    // {{{ class constants

    /**
     * Class constant indicating session id should be regenerated
     *
     * @see SiteAccountSessionModule::login()
     * @see SiteAccountSessionModule::loginById()
     * @see SiteAccountSessionModule::loginByAccount()
     */
    const REGENERATE_ID = true;

    /**
     * Class constant indicating session id should not be regenerated
     *
     * @see SiteAccountSessionModule::login()
     * @see SiteAccountSessionModule::loginById()
     * @see SiteAccountSessionModule::loginByAccount()
     */
    const NO_REGENERATE_ID = false;

    // }}}
    // {{{ protected properties

    /**
     * @var array
     */
    protected $regenerate_id_callbacks = array();

    /**
     * @var array
     */
    protected $activate_callbacks = array();

    /**
     * @var array
     *
     * @see SiteSessionModule::registerObject()
     */
    protected $registered_objects = array();

    // }}}
    // {{{ public function __construct()

    /**
     * Creates a site session module
     *
     * @param SiteApplication $app the application this module belongs to.
     *
     * @throws SiteException if there is no cookie module loaded the session
     *                         module throws an exception.
     *
     * @throws SiteException if there is no database module loaded the session
     *                         module throws an exception.
     */
    public function __construct(SiteApplication $app)
    {
        $this->registerActivateCallback(array(
            $this,
            'regenerateAuthenticationToken'
        ));

        $this->registerActivateCallback(array($this, 'setSentryUserContext'));

        $this->registerRegenerateIdCallback(array(
            $this,
            'regenerateAuthenticationToken'
        ));

        $this->registerRegenerateIdCallback(array(
            $this,
            'setSentryUserContext'
        ));

        parent::__construct($app);
    }

    // }}}
    // {{{ public function init()

    /**
     * Initializes this session module
     */
    public function init()
    {
        $session_name = $this->getSessionName();

        session_cache_limiter('');
        session_name($session_name);

        $path = $this->app->config->session->path . '/' . $session_name;
        $this->setSavePath($path);

        if ($this->shouldAutoActivateSession()) {
            $this->autoActivate();
        }
    }

    // }}}
    // {{{ public function depends()

    /**
     * Gets the module features this module depends on
     *
     * The site session module optionally depends on the
     * SiteConfigModule and SiteMultipleInstanceModule features.
     *
     * @return array an array of {@link SiteApplicationModuleDependency}
     *                        objects defining the features this module
     *                        depends on.
     */
    public function depends()
    {
        $depends = parent::depends();
        $depends[] = new SiteApplicationModuleDependency('SiteConfigModule');
        $depends[] = new SiteApplicationModuleDependency(
            'SiteMultipleInstanceModule',
            false
        );

        return $depends;
    }

    // }}}
    // {{{ public function activate()

    /**
     * Activates the current user's session
     *
     * Subsequent calls to the {@link isActive()} method will return true.
     */
    public function activate()
    {
        if ($this->isActive()) {
            return;
        }

        $this->startSession();

        /*
         * Store the user agent in the session to mitigate the risk of session
         * hijacking. See the autoActivate() method for details.
         */
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $this->_user_agent = $_SERVER['HTTP_USER_AGENT'];
        }

        foreach ($this->activate_callbacks as $callback) {
            $function = $callback['callback'];
            $parameters = $callback['parameters'];
            call_user_func_array($function, $parameters);
        }
    }

    // }}}
    // {{{ public function isActive()

    /**
     * Checks if there is an active session
     *
     * @return boolean true if session is active, false if the session is
     *                  inactive.
     */
    public function isActive()
    {
        return session_id() != '';
    }

    // }}}
    // {{{ public function registerActivateCallback()

    /**
     * Registers a callback function that is executed when the session is
     * activated
     *
     * @param callback $callback the callback to call when the session is
     *                            activated.
     * @param array $parameters optional. The paramaters to pass to the
     *                           callback. Use an empty array for no parameters.
     */
    public function registerActivateCallback(
        $callback,
        array $parameters = array()
    ) {
        if (!is_callable($callback)) {
            throw new SiteException('Cannot register invalid callback.');
        }

        $this->activate_callbacks[] = array(
            'callback' => $callback,
            'parameters' => $parameters
        );
    }

    // }}}
    // {{{ public function registerObject()

    /**
     * Registers an object class for a session variable
     *
     * Registering the object class for a session variable does three things:
     *
     *  1. Wraps errors when unserializing an unknown class from the session in
     *     a catchable exception.
     *  2. Automatically restores database connections for classes that are
     *     SwatDBRecordable.
     *  3. Provides a mechanism to clear a subset of the registered variables.
     *
     * Note: If a autoloader can be used to automatically load the appropriate
     * class definitions when the session is restored.
     *
     * @param string $name the name of the session variable.
     * @param string $class the object class name.
     * @param boolean $destroy_on_logout whether or not to destroy the object
     *                                    on logout.
     */
    public function registerObject($name, $class, $destroy_on_logout = true)
    {
        $this->registered_objects[$name] = array(
            'class' => $class,
            'destroy' => $destroy_on_logout ? true : false
        );
    }

    // }}}
    // {{{ public function unsetRegisteredObjects()

    /**
     * Unsets variables registered in the session and marked as
     * destroy-on-logout
     */
    public function unsetRegisteredObjects()
    {
        foreach ($this->registered_objects as $name => $data) {
            if ($data['destroy']) {
                unset($this->$name);
            }
        }
    }

    // }}}
    // {{{ public function getSessionId()

    /**
     * Retrieves the current session ID
     *
     * @return integer the current session ID, or null if no active session.
     */
    public function getSessionId()
    {
        if (!$this->isActive()) {
            return null;
        }

        return session_id();
    }

    // }}}
    // {{{ public function getSessionName()

    /**
     * Retrieves the session name
     *
     * @return string the name to use for the session.
     */
    public function getSessionName()
    {
        $name = $this->app->config->session->name;
        return $name;
    }

    // }}}
    // {{{ public function clear()

    /**
     * Clears all session variables while maintaining the current session
     */
    public function clear()
    {
        if (!$this->isActive()) {
            $_SESSION = array();
        }
    }

    // }}}
    // {{{ public function appendSessionId()

    /**
     * Appends the session ID to a URI
     *
     * Appends the session identifier (SID) to the URI if necessary.
     * This method is called by SiteApplication::relocate() before relocating
     * to the URI.
     *
     * @param string $uri the URI to be relocated to.
     * @param boolean $append_sid optional. Whether or not to append the
     *                             session identifier to the URI. If null, this
     *                             is determined automatically. Automatic
     *                             determination works as follows: If the
     *                             <i>$uri</i> is relative and no session
     *                             cookie exists, the session identifier is
     *                             appended to the URI.
     */
    public function appendSessionId($uri, $append_sid = null)
    {
        if ($this->isActive()) {
            if ($append_sid === null) {
                $is_relative_uri = mb_strpos($uri, '://') === false;
                $has_cookie = isset($_COOKIE[$this->getSessionName()]);
                $append_sid = $is_relative_uri && !$has_cookie;
            }

            if ($append_sid) {
                $sid = sprintf(
                    '%s=%s',
                    $this->getSessionName(),
                    $this->getSessionId()
                );

                if (mb_strpos($uri, $sid) === false) {
                    if (mb_strpos($uri, '?') === false) {
                        $uri .= '?';
                    } else {
                        $uri .= '&';
                    }

                    $uri .= sprintf(
                        '%s=%s',
                        $this->getSessionName(),
                        $this->getSessionId()
                    );
                }
            }
        }

        return $uri;
    }

    // }}}
    // {{{ public function setSavePath()

    /**
     * Set the session save path
     */
    public function setSavePath($path)
    {
        session_save_path($path);
    }

    // }}}
    // {{{ public function getSavePath()

    /**
     * Get the session save path
     */
    public function getSavePath()
    {
        return session_save_path();
    }

    // }}}
    // {{{ public function autoActivate()

    /**
     * Activates the current user's session
     *
     * Subsequent calls to the {@link isActive()} method will return true.
     */
    public function autoActivate()
    {
        if ($this->isActive()) {
            return;
        }

        $this->startSession();

        /*
         * The user agent is stored in the session to reduce accidental session
         * sharing due to copy/pasting/sending of links with session IDs.
         * Spaces are stripped before comparing to workaround browsers which
         * provide a slightly different user agent to scripts.
         */
        if (
            isset($_SERVER['HTTP_USER_AGENT']) &&
            isset($this->_user_agent) &&
            str_replace(' ', '', $this->_user_agent) !==
                str_replace(' ', '', $_SERVER['HTTP_USER_AGENT'])
        ) {
            /*
             * Clean and relocate to the current URI if it has a session ID
             * in it. SwatWebApplication::getUri() can not be used here since
             * this runs before SwatWebApplication::parseUri().
             */
            $session_name = $this->getSessionName();
            if (isset($_GET[$session_name])) {
                $regexp = sprintf(
                    '/%s=[^&]*&?/u',
                    preg_quote($session_name, '/')
                );

                $uri = preg_replace($regexp, '', $_SERVER['REQUEST_URI']);
                $this->app->relocate($uri, null, false);
            }
        }

        $this->setSentryUserContext();
    }

    // }}}
    // {{{ public function regenerateId()

    public function regenerateId()
    {
        $old_id = $this->getSessionId();

        // regenerate id, this resends the session cookie
        session_regenerate_id();

        $new_id = $this->getSessionId();

        foreach ($this->regenerate_id_callbacks as $callback) {
            $function = $callback['callback'];
            $parameters = $callback['parameters'];

            // if there are no parameters, use old_id and new_id as parameters
            if ($parameters == null) {
                $parameters = array($old_id, $new_id);
            }

            call_user_func_array($function, $parameters);
        }
    }

    // }}}
    // {{{ public function sessionFileExists()

    /**
     * Checks to see if a session file exists for a specific session_id
     *
     * @param string $session_id the id of the session to check
     *
     * @return boolean True if the session exists or false if it doesn't
     */
    public function sessionFileExists($session_id)
    {
        $exists = false;

        if ($this->isValidSessionId($session_id)) {
            $exists = file_exists(
                sprintf('%s/sess_%s', $this->getSavePath(), $session_id)
            );
        }

        return $exists;
    }

    // }}}
    // {{{ public function registerRegenerateIdCallback()

    /**
     * Registers a callback function that is executed when the session ID
     * is regenerated
     *
     * @param callback $callback the callback to call when regenerating session
     *                            ID.
     * @param array $parameters optional. The paramaters to pass to the
     *                           callback. If no parameters or null is
     *                           specified, the old session id and new session
     *                           id are used as parameters. Explicitly use an
     *                           empty array to specify no parameters. Use a
     *                           single-element array containing null to
     *                           specify null as a parameter.
     */
    public function registerRegenerateIdCallback($callback, $parameters = null)
    {
        if (!is_callable($callback)) {
            throw new SiteException('Cannot register invalid callback.');
        }

        if ($parameters !== null && !is_array($parameters)) {
            throw new SiteException(
                'Callback parameters must be specified ' . 'in an array.'
            );
        }

        $this->regenerate_id_callbacks[] = array(
            'callback' => $callback,
            'parameters' => $parameters
        );
    }

    // }}}
    // {{{ public function __set()

    /**
     * Sets a session variable
     *
     * @param string $name the name of the session variable to set.
     * @param mixed $value the value to set the variable to.
     */
    public function __set($name, $value)
    {
        if (!$this->isActive()) {
            throw new SiteException('Session is  not active.');
        }

        $_SESSION[$name] = $value;
    }

    // }}}
    // {{{ public function __isset()

    /**
     * Checks the existence of a session variable
     *
     * If the session is not active this method always returns false.
     *
     * @param string $name the name of the session variable to check.
     */
    public function __isset($name)
    {
        return $this->isActive() && isset($_SESSION[$name]);
    }

    // }}}
    // {{{ public function __unset()

    /**
     * Removes a session variable
     *
     * @param string $name the name of the session variable to set.
     */
    public function __unset($name)
    {
        if (!$this->isActive()) {
            throw new SiteException('Session is not active.');
        }

        if (isset($_SESSION[$name])) {
            unset($_SESSION[$name]);
        }
    }

    // }}}
    // {{{ public function __get()

    /**
     * Gets a session variable
     *
     * @param string $name the name of the session variable to get.
     *
     * @return mixed the session variable value.
     */
    public function __get($name)
    {
        if (!$this->isActive()) {
            throw new SiteException('Session is not active.');
        }

        if (!array_key_exists($name, $_SESSION)) {
            throw new SiteException(
                "Session variable '{$name}' does not exist."
            );
        }

        return $_SESSION[$name];
    }

    // }}}
    // {{{ protected function regenerateAuthenticationToken()

    protected function regenerateAuthenticationToken()
    {
        $this->_authentication_token = SwatString::hash(mt_rand());
        SwatForm::setAuthenticationToken($this->_authentication_token);
    }

    // }}}
    // {{{ protected function shouldAutoActivateSession()

    /**
     * Whether to auto activate a session during module init
     */
    protected function shouldAutoActivateSession()
    {
        if (isset($_SERVER['REDIRECT_STATUS'])) {
            switch ($_SERVER['REDIRECT_STATUS']) {
                case '403':
                case '504':
                case '500':
                    return false;
            }
        }

        $session_name = $this->getSessionName();
        $found = false;

        if (isset($_GET[$session_name])) {
            if ($this->isValidSessionId($_GET[$session_name])) {
                $found = true;
            } else {
                unset($_GET[$session_name]);
            }
        }

        if (isset($_POST[$session_name])) {
            if ($this->isValidSessionId($_POST[$session_name])) {
                $found = true;
            } else {
                unset($_POST[$session_name]);
            }
        }

        if (isset($_COOKIE[$session_name])) {
            if ($this->isValidSessionId($_COOKIE[$session_name])) {
                $found = true;
            } else {
                unset($_COOKIE[$session_name]);
            }
        }

        return $found;
    }

    // }}}
    // {{{ protected function startSession()

    /**
     * Starts a session
     */
    protected function startSession()
    {
        // Make sure registered object classes exist before starting the
        // session.
        $this->checkRegisteredObjectClasses();

        // Start the session.
        session_start();

        // Explicitly set the session cookie since PHP doesn't do this
        // sometimes on SSL requests.
        if (ini_get('session.use_cookies') == 1) {
            $cookie_name = session_name();
            $cookie_value = session_id();

            setcookie($cookie_name, $cookie_value, 0, '/');

            // Also explicitly set $_COOKIE since its value is only accessible
            // on subsequent page loads and we may need the value during the
            // remainder of the current request.
            $_COOKIE[$cookie_name] = $cookie_value;
        }

        $this->restoreRegisteredObjectDBConnections();

        // The auth token should always be set. Some old sessions may not
        // have it set so generate one. See silverorange/site#41
        if (isset($this->_authentication_token)) {
            SwatForm::setAuthenticationToken($this->_authentication_token);
        } else {
            $this->regenerateAuthenticationToken();
        }
    }

    // }}}
    // {{{ protected function restoreRegisteredObjectDBConnections()

    /**
     * Restores the database connection resource for objects registered in
     * the session that are instances of {@link SwatDBRecordable}
     */
    protected function restoreRegisteredObjectDBConnections()
    {
        if (!$this->app->hasModule('SiteDatabaseModule')) {
            return;
        }

        $database = $this->app->getModule('SiteDatabaseModule');
        $connection = $database->getConnection();

        foreach ($this->registered_objects as $name => $data) {
            if (isset($this->$name)) {
                if (
                    is_array($this->$name) ||
                    $this->$name instanceof ArrayObject
                ) {
                    foreach ($this->$name as $object) {
                        if ($object instanceof SwatDBRecordable) {
                            $object->setDatabase($connection);
                        }
                    }
                } elseif ($this->$name instanceof SwatDBRecordable) {
                    $this->$name->setDatabase($connection);
                }
            } else {
                $this->$name = null;
            }
        }
    }

    // }}}
    // {{{ protected function checkRegisteredObjectClasses()

    /**
     * Checks to make sure class definitions exist for objects registered in the
     * session
     *
     * @throws SwatClassNotFoundException if one or more class definitions do
     *         not exist for the registered objects.
     */
    protected function checkRegisteredObjectClasses()
    {
        foreach ($this->registered_objects as $name => $data) {
            $class = $data['class'];
            if (!class_exists($class)) {
                throw new SwatClassNotFoundException(
                    sprintf(
                        'The class "%s" does not exist. The class definition must ' .
                            'be loaded before the session is restored.',
                        $class
                    ),
                    0,
                    $class
                );
            }
        }
    }

    // }}}
    // {{{ protected function getErrorUserContext()

    /**
     * Gets the user-context array for error reporting
     *
     * @return array the user-context array for error reporting.
     */
    protected function getErrorUserContext()
    {
        $data = [];

        if ($this->isActive()) {
            $data = [
                'session_id' => $this->getSessionId(),
                'data' => $_SESSION
            ];

            if (!empty($_SERVER['REMOTE_ADDR'])) {
                $data['ip_address'] = $_SERVER['REMOTE_ADDR'];
            }
        }

        return $data;
    }

    // }}}
    // {{{ protected function setSentryUserContext()

    /**
     * Sets the user-context for the sentry module if the application has a
     * sentry module
     */
    protected function setSentryUserContext()
    {
        $client = $this->app->getSentryClient();
        if ($client instanceof Raven_Client) {
            $client->user_context($this->getErrorUserContext());
        }
    }

    // }}}
    // {{{ private function isValidSessionId()

    private function isValidSessionId($id)
    {
        $valid = false;

        if (preg_match('/^[a-zA-Z0-9\-,]+$/', $id) === 1) {
            $valid = true;
        }

        return $valid;
    }

    // }}}
}
