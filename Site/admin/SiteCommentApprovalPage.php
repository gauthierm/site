<?php

/**
 * Abstract approval page
 *
 * @package   Site
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteCommentApprovalPage extends AdminApproval
{
    // {{{ protected function initInternal()

    protected function initInternal()
    {
        parent::initInternal();
        $this->ui->getWidget('delete_button')->title = Site::_('Deny');
    }

    // }}}

    // {{{ protected function approve()

    protected function approve()
    {
        $this->data_object->status = SiteComment::STATUS_PUBLISHED;
        $this->data_object->save();
        $this->data_object->postSave($this->app);
    }

    // }}}
    // {{{ protected function delete()

    protected function delete()
    {
        $this->data_object->delete();
        $this->data_object->clearCache($this->app);
    }

    // }}}
}
