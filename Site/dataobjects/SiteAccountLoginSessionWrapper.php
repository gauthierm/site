<?php

/**
 * A recordset wrapper class for SiteAccountLoginSession objects
 *
 * @package   Site
 * @copyright 2012-2016 silverorange
 * @see       SiteAccountLoginSession
 */
class SiteAccountLoginSessionWrapper extends SwatDBRecordsetWrapper
{
    // {{{ protected function init()

    protected function init()
    {
        parent::init();

        $this->row_wrapper_class = SwatDBClassMap::get(
            'SiteAccountLoginSession'
        );

        $this->index_field = 'id';
    }

    // }}}
}
