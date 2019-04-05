<?php

/**
 * Cell renderer that displays a summary of the visibility of an article
 *
 * @package   Site
 * @copyright 2005-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteArticleVisibilityCellRenderer extends SwatCellRenderer
{
    // {{{ public properties

    public $enabled = false;
    public $searchable = false;
    public $show_in_menu = false;

    public $display_positive_states = false;
    public $separator = ', ';

    // }}}
    // {{{ public function render()

    public function render()
    {
        $messages = array();

        if (!$this->enabled) {
            $messages[] = Site::_('not enabled');
        } elseif ($this->display_positive_states) {
            $messages[] = Site::_('enabled');
        }

        if (!$this->searchable) {
            $messages[] = Site::_('not searchable');
        } elseif ($this->display_positive_states) {
            $messages[] = Site::_('searchable');
        }

        if (!$this->show_in_menu) {
            $messages[] = Site::_('not shown in menu');
        } elseif ($this->display_positive_states) {
            $messages[] = Site::_('shown in menu');
        }

        echo implode($this->separator, $messages);
    }

    // }}}
}
