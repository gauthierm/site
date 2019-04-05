<?php

/**
 * A module to profile web-applications
 *
 * @package   Site
 * @copyright 2004-2008
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteTimerModule extends SiteApplicationModule
{
    // {{{ private properties

    /**
     * The execution start time of this application
     *
     * @var double
     */
    private $start_time = null;

    /**
     * A set of ended timer checkpoints used by this module
     *
     * @var array
     */
    private $checkpoints = array();

    /**
     * A set of started timer checkpoints used by this module
     *
     * @var array
     */
    private $started_checkpoints = array();

    // }}}
    // {{{ public function init()

    /**
     * Initializes this timer module
     */
    public function init()
    {
        $this->reset();
    }

    // }}}
    // {{{ public function getTime()

    /**
     * Gets the current execution time of this application in milliseconds
     *
     * @return double the current execution time of this application in
     *                 milliseconds.
     */
    public function getTime()
    {
        return microtime(true) * 1000 - $this->start_time;
    }

    // }}}
    // {{{ public function startCheckpoint()

    /**
     * Sets a timer checkpoint
     *
     * @param string $name the name of the checkpoint
     */
    public function startCheckpoint($name)
    {
        $this->started_checkpoints[$name] = new SiteTimerCheckpoint(
            $name,
            $this->getTime(),
            memory_get_usage()
        );
    }

    // }}}
    // {{{ public function endCheckpoint()

    /**
     * Ends a timer checkpoint
     *
     * @param string $name the name of the checkpoint
     */
    public function endCheckpoint($name)
    {
        if (array_key_exists($name, $this->started_checkpoints)) {
            $checkpoint = $this->started_checkpoints[$name];
            $time_delta = $this->getTime() - $checkpoint->getTime();
            $memory_delta = memory_get_usage() - $checkpoint->getMemoryUsage();
            $this->checkpoints[$name] = new SiteTimerCheckpoint(
                $name,
                $time_delta,
                $memory_delta
            );

            unset($this->started_checkpoints[$name]);
        }
    }

    // }}}
    // {{{ public function display()

    /**
     * Displays a summary of all checkpoints and the total time of this timer
     * module
     */
    public function display()
    {
        $locale = SwatI18NLocale::get();

        echo '<dl style="position: fixed; top: 10px; right: 10px; ';
        echo 'opacity: 0.8; padding: 1em; font-family: sans-serif; ';
        echo 'font-weight: bold; color: #000; border: 2px solid #666; ';
        echo 'background: #eee; font-size: 11px; z-index: 200;">';

        $dt_tag = new SwatHtmlTag('dt');
        $dd_tag = new SwatHtmlTag('dd');

        // display checkpoints
        foreach ($this->checkpoints as $checkpoint) {
            $time = $locale->formatNumber($checkpoint->getTime(), 3);
            $bytes = SwatString::byteFormat($checkpoint->getMemoryUsage(), 0);

            $dt_tag->setContent($checkpoint->getName());
            $dt_tag->display();
            $dd_tag->setContent(sprintf(Site::_('%s ms - %s'), $time, $bytes));
            $dd_tag->display();
        }

        // display total time and peak memory
        $time = $locale->formatNumber($this->getTime(), 3);
        $bytes = SwatString::byteFormat(memory_get_peak_usage(), 0);

        $dt_tag->setContent(Site::_('Total'));
        $dt_tag->class = 'site-timer-module-total';
        $dt_tag->display();
        $dd_tag->setContent(
            sprintf(Site::_('%s ms - %s (peak)'), $time, $bytes)
        );

        $dd_tag->class = 'site-timer-module-total';
        $dd_tag->display();

        echo '</dl>';
    }

    // }}}
    // {{{ protected function reset()

    /**
     * Resets this timer
     *
     * All checkpoints are cleared.
     */
    protected function reset()
    {
        $this->start_time = microtime(true) * 1000;
        $this->checkpoints = array();
    }

    // }}}
}
