<?php

/**
 * Page for editing comments
 *
 * @package   Site
 * @copyright 2009-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteCommentEdit extends AdminDBEdit
{
    // {{{ protected properties

    /**
     * @var SiteComment
     */
    protected $comment;

    // }}}

    // init phase
    // {{{ protected function initInternal()

    protected function initInternal()
    {
        parent::initInternal();

        $this->ui->loadFromXML($this->getUiXml());
        $this->initComment();
    }

    // }}}
    // {{{ protected function getUiXml()

    protected function getUiXml()
    {
        return __DIR__ . '/edit.xml';
    }

    // }}}
    // {{{ protected function initComment()

    protected function initComment()
    {
        $this->comment = $this->getComment();
        $this->comment->setDatabase($this->app->db);

        if ($this->id !== null) {
            if (!$this->comment->load($this->id, $this->app->getInstance())) {
                throw new AdminNotFoundException(
                    sprintf('Comment with id ‘%s’ not found.', $this->id)
                );
            }
        }
    }

    // }}}
    // {{{ protected function getComment()

    protected function getComment()
    {
        $class_name = SwatDBClassMap::get('SiteComment');
        return new $class_name();
    }

    // }}}

    // process phase
    // {{{ protected function saveDBData()

    protected function saveDBData()
    {
        $values = $this->ui->getValues(array(
            'fullname',
            'link',
            'email',
            'bodytext',
            'status'
        ));

        if ($this->comment->id === null) {
            $now = new SwatDate();
            $now->toUTC();
            $this->comment->createdate = $now;
        }

        $this->comment->fullname = $values['fullname'];
        $this->comment->link = $values['link'];
        $this->comment->email = $values['email'];
        $this->comment->status = $values['status'];

        if ($this->comment->status === null) {
            $this->comment->status = SiteComment::STATUS_PUBLISHED;
        }

        $this->comment->bodytext = $values['bodytext'];

        if ($this->comment->isModified()) {
            $this->comment->save();
            $this->comment->postSave($this->app);

            $message = new SwatMessage(Site::_('Comment has been saved.'));

            $this->app->messages->add($message);
        }
    }

    // }}}

    // build phase
    // {{{ protected function buildInternal()

    protected function buildInternal()
    {
        parent::buildInternal();

        $statuses = SiteComment::getStatusArray();
        $this->ui->getWidget('status')->addOptionsByArray($statuses);
    }

    // }}}
    // {{{ protected function loadDBData()

    protected function loadDBData()
    {
        $this->ui->setValues($this->comment->getAttributes());
    }

    // }}}
}
