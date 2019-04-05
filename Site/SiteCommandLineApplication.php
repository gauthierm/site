<?php

/**
 * An application designed to run on the command line
 *
 * This class handles the creating and parsing of command line arguments and
 * has the ability to display usage information.
 *
 * Command line applications must implement the {@link SiteApplication::run()}
 * method.
 *
 * @package   Site
 * @copyright 2006-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteCommandLineApplication extends SiteApplication
{
    // {{{ class constants

    /**
     * Verbosity level for showing nothing.
     */
    const VERBOSITY_NONE = 0;

    /**
     * Verbosity level for showing error messages.
     *
     * @see SiteCommandLineApplication::error()
     */
    const VERBOSITY_ERRORS = 1;

    /**
     * Verbosity level for showing all actions
     *
     * @see SiteCommandLineApplication::debug()
     */
    const VERBOSITY_ALL = 2;

    // }}}
    // {{{ protected properties

    /**
     * An array of {@link SiteCommandLineArgument} objects used by this
     * application
     *
     * @var array
     */
    protected $arguments = array();

    /**
     * The title of this application
     *
     * The title is displayed in error messages and in usage information.
     *
     * @var string
     */
    protected $title;

    /**
     * Text describing the purpose of this application
     *
     * This is displayed in usage information.
     *
     * @var string
     */
    protected $documentation;

    /**
     * The level of verbosity to use
     *
     * This controls the output of {@link SiteCommandLineApplication::output()}.
     *
     * @var integer
     *
     * @see SiteCommandLineApplication::setVerbosity()
     */
    protected $verbosity = 0;

    /**
     * The opened lock file
     *
     * @var resource
     *
     * @see SiteCommandLineApplication::lock()
     * @see SiteCommandLineApplication::unlock()
     */
    protected $lock_file;

    // }}}
    // {{{ public function __construct()

    /**
     * Creates a new command line application
     *
     * By default, the 'help' and 'verbosity' command line arguments are added.
     * This 'help' argument is set to display usage information and the
     * 'verbosity' argument sets the level of debug output.
     *
     * @param string $id a unique identifier for this application.
     * @param string $config_filename the filename of the configuration file.
     *                                 If specified as null, no configuration
     *                                 module is created and no special
     *                                 configuration is performed.
     * @param string $title the title of this application.
     * @param string $documentation optional text describing the purpose of
     *                               this application.
     *
     * @throws SiteCommandLineException if this application is not created in a
     *                                   commandline environment.
     */
    public function __construct(
        $id,
        $config_filename,
        $title,
        $documentation = null
    ) {
        if (!isset($_SERVER['argv'])) {
            throw new SiteCommandLineException(
                'Command line applications ' .
                    'must be run from the command line.'
            );
        }

        parent::__construct($id, $config_filename);

        $this->title = $title;
        $this->documentation = $documentation;

        // add help
        $help_argument = new SiteCommandLineArgument(
            array('-?', '-h', '--help'),
            'displayUsage',
            Site::_('Displays this usage information and exits.')
        );

        $this->addCommandLineArgument($help_argument);

        // add verbosity
        $verbosity = new SiteCommandLineArgument(
            array('-v', '--verbose'),
            'setVerbosity',
            Site::_(
                'Sets the level of verbosity of this application. Pass 0 ' .
                    'to turn off all output.'
            )
        );

        $verbosity->addParameter(
            'integer',
            Site::_('--verbose expects a level between 0 and 2.'),
            self::VERBOSITY_ALL
        );

        $this->addCommandLineArgument($verbosity);
    }

    // }}}
    // {{{ public function addCommandLineArgument()

    /**
     * Adds a command line argument to this application
     *
     * Command line arguments may be added either when the class is used or
     * in the class definition.
     *
     * For example, to create a command line argument accepting either '-f' or
     * '--foo' that runs the foo() method, use the following:
     *
     * <code>
     * $foo_argument = new SiteCommandLineArgument(array('-f', '--foo'), 'foo',
     *     'Runs the foo() method.');
     *
     * $app->addCommandLineArgument($foo_argument);
     * </code>
     *
     * @param SiteCommandLineArgument $argument the command line argument to
     *                                           add.
     */
    public function addCommandLineArgument(SiteCommandLineArgument $argument)
    {
        $this->arguments[] = $argument;
    }

    // }}}
    // {{{ public function setVerbosity()

    /**
     * Sets the level of verbosity to use for this application
     *
     * @param integer $verbosity the level of verbosity to use.
     */
    public function setVerbosity($verbosity)
    {
        $this->verbosity = (int) $verbosity;
    }

    // }}}
    // {{{ public function setApplicationDirectory()

    /**
     * Sets the application directory
     *
     * @param string $directory the directory to use.
     */
    public function setApplicationDirectory($directory)
    {
        chdir($directory);
    }

    // }}}
    // {{{ public function displayUsage()

    /**
     * Displays usage information for this command line application
     */
    public function displayUsage()
    {
        echo $this->title, "\n\n";
        if ($this->documentation !== null) {
            echo $this->documentation, "\n\n";
        }

        echo Site::_('OPTIONS'), "\n";

        foreach ($this->arguments as $argument) {
            $this->displayArgumentUsage($argument);
        }

        exit(0);
    }

    // }}}
    // {{{ public function displayArgumentUsage()

    /**
     * Displays usage information for a single command line argument
     *
     * @param SiteCommandLineArgument $argument the command line argument for
     *                                           which to display usage
     *                                           information.
     */
    public function displayArgumentUsage(SiteCommandLineArgument $argument)
    {
        echo implode(', ', $argument->getNames()),
            "\n",
            '   ',
            $argument->getDocumentation(),
            "\n\n";
    }

    // }}}
    // {{{ public function getScriptDirectory()

    /**
     * Gets an absolute path to the directory in which the currently running
     * script resides
     *
     * @return string the absolute path to the directory in which the currently
     *                running script resides.
     */
    public function getScriptDirectory()
    {
        return dirname(realpath($_SERVER['SCRIPT_FILENAME']));
    }

    // }}}
    // {{{ public function run()

    /**
     * Run the application. Includes boilerplate init of modules and command
     * line arguments.
     */
    public function run()
    {
        $this->initModules();
        $this->parseCommandLineArguments();
    }

    // }}}
    // {{{ protected function parseCommandLineArguments()

    /**
     * Automatically parses and interprets command line arguments of this
     * application
     *
     * @see SiteCommandLineApplication::addCommandLineArgument()
     */
    protected function parseCommandLineArguments()
    {
        $reflector = new ReflectionClass(get_class($this));

        $args = $_SERVER['argv'];
        $num_args = count($args);
        for ($i = 1; $i < $num_args; $i++) {
            $found_argument = false;
            foreach ($this->arguments as $argument) {
                $argument_parameters = array();
                if (in_array($args[$i], $argument->getNames())) {
                    foreach ($argument->getParameters() as $parameter) {
                        if (isset($args[$i + 1]) && $args[$i + 1][0] !== '-') {
                            $i++;
                            if ($parameter->validate($args[$i])) {
                                $argument_parameters[] = $args[$i];
                            } else {
                                printf(
                                    Site::_('%s: %s'),
                                    $this->title,
                                    $parameter->getErrorMessage()
                                );

                                echo "\n";
                                exit(1);
                            }
                        } elseif ($parameter->hasDefault()) {
                            $argument_parameters[] = $parameter->getDefault();
                        } else {
                            printf(
                                Site::_('%s: %s'),
                                $this->title,
                                $parameter->getErrorMessage()
                            );

                            echo "\n";
                            exit(1);
                        }
                    }

                    if (!$reflector->hasMethod($argument->getMethod())) {
                        throw new SiteCommandLineException(
                            sprintf(
                                "Application argument calls undefined method '%s'.",
                                $argument->getMethod()
                            )
                        );
                    }

                    $method = $reflector->getMethod($argument->getMethod());
                    if ($argument->hasParameter()) {
                        $method->invokeArgs($this, $argument_parameters);
                    } else {
                        if ($method->getNumberOfRequiredParameters() > 0) {
                            $method->invoke($this, true);
                        } else {
                            $method->invoke($this);
                        }
                    }

                    $found_argument = true;
                }
            }
            if (!$found_argument) {
                printf(
                    Site::_("%s: unknown command line argument '%s'"),
                    $this->title,
                    $args[$i]
                );

                echo "\n";

                exit(1);
            }
        }
    }

    // }}}
    // {{{ protected function output()

    /**
     * Displays a string based on the verbosity level of this application
     *
     * @param string $string the string to display.
     * @param integer $verbosity the verbosity level to display at. If this
     *                            application's verbosity is less than this
     *                            level, the string is not displayed.
     * @param boolean $bold optional. Whether or not to display the string
     *                       using a bold font on supported terminals. Defaults
     *                       to false.
     */
    protected function output($string, $verbosity, $bold = false)
    {
        if ($verbosity <= $this->verbosity) {
            if ($bold) {
                $term = SiteApplication::initVar(
                    'TERM',
                    '',
                    SiteApplication::VAR_ENV
                );

                $bold = mb_strpos($term, 'xterm') !== false;
            }

            if ($bold) {
                echo "\033[1m", $string, "\033[0m";
            } else {
                echo $string;
            }
        }
    }

    // }}}
    // {{{ protected function debug()

    /**
     * Displays output at the verbosity level
     * {@link SiteCommandLineApplication::VERBOSITY_ALL}
     *
     * @param string $string the string to display.
     * @param boolean $bold optional. Whether or not to display the string
     *                       using a bold font on supported terminals. Defaults
     *                       to false.
     */
    protected function debug($string, $bold = false)
    {
        $this->output($string, self::VERBOSITY_ALL, $bold);
    }

    // }}}
    // {{{ protected function error()

    /**
     * Displays output at the verbosity level
     * {@link SiteCommandLineApplication::VERBOSITY_ERRORS}
     *
     * @param string $string the string to display.
     * @param boolean $bold optional. Whether or not to display the string
     *                       using a bold font on supported terminals. Defaults
     *                       to false.
     */
    protected function error($string, $bold = false)
    {
        $this->output($string, self::VERBOSITY_ERRORS, $bold);
    }

    // }}}
    // {{{ protected function terminate()

    /**
     * Terminates this application and displays the specified error message
     *
     * Program execution ceases after this method is run. Code present in
     * object destructors will still run.
     *
     * @param string $string the string to dipslay.
     * @param integer $verbosity optional. the verbosity level to display at. If
     *                            this application's verbosity is less than this
     *                            level, the string is not displayed. Defaults
     *                            to {@link SiteCommandLineApplication::VERBOSITY_ERRORS}.
     * @param integer $error_code optional. The error code with which to
     *                             terminate execution. If not specified, 1 is
     *                             used.
     */
    protected function terminate(
        $string,
        $verbosity = self::VERBOSITY_ERRORS,
        $error_code = 1
    ) {
        $this->output($string, $verbosity);
        exit((int) $error_code);
    }

    // }}}
    // {{{ protected function lock()

    /**
     * Locks this application, preventing more than one instance from running
     */
    protected function lock()
    {
        /*
         * Note: PHP closes the file and releases all locks upon script
         * termination. Because of this, the lock is never unreleased through
         * unexpected script terminiation. The lock file is deleted upon
         * successful script termination (in unlock()).
         */
        $filename = $this->getLockFilename();

        // make sure lock file exists
        if (!file_exists($filename)) {
            touch($filename);
        }

        // open lock
        $this->lock_file = fopen($filename, 'rb');

        if ($this->lock_file === false) {
            $this->terminate(
                sprintf(Site::_("Error opening lock file: '%s'\n"), $filename)
            );
        }

        // try to lock
        $has_lock = flock($this->lock_file, LOCK_EX | LOCK_NB);
        if (!$has_lock) {
            fclose($this->lock_file);
            $this->terminate(
                sprintf(Site::_("%s is already running.\n"), $this->title),
                self::VERBOSITY_ALL
            );
        }
    }

    // }}}
    // {{{ protected function unlock()

    /**
     * Unlocks this application, allowing more than one instance to run
     */
    protected function unlock()
    {
        // unlock lock file
        flock($this->lock_file, LOCK_UN);
        fclose($this->lock_file);

        // delete the lock file if it exists
        $filename = $this->getLockFilename();
        if (file_exists($filename)) {
            unlink($filename);
        } else {
            $this->error(Site::_("Lock file is missing when unlocking\n"));
        }
    }

    // }}}
    // {{{ protected function getLockFilename()

    protected function getLockFilename()
    {
        $directory = realpath(dirname($_SERVER['SCRIPT_NAME']));
        $filename = '.' . $this->id . '.lock';
        return $directory . DIRECTORY_SEPARATOR . $filename;
    }

    // }}}
}
