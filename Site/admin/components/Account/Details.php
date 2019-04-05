<?php

/**
 * Details page for accounts
 *
 * @package   Site
 * @copyright 2006-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteAccountDetails extends AdminIndex
{
    // {{{ protected properties

    /**
     * @var integer
     */
    protected $id;

    /**
     * @var SiteAccount
     */
    protected $account;

    // }}}

    // init phase
    // {{{ protected function initInternal()

    protected function initInternal()
    {
        parent::initInternal();

        $this->ui->mapClassPrefixToPath('Site', 'Site');
        $this->ui->loadFromXML($this->getUiXml());

        $this->id = SiteApplication::initVar('id');
    }

    // }}}
    // {{{ protected function getAccount()

    protected function getAccount()
    {
        if ($this->account === null) {
            $account_class = SwatDBClassMap::get('SiteAccount');

            $this->account = new $account_class();
            $this->account->setDatabase($this->app->db);

            if (!$this->account->load($this->id)) {
                throw new AdminNotFoundException(
                    sprintf(
                        Site::_(
                            'An account with an id of ‘%d’ does not exist.'
                        ),
                        $this->id
                    )
                );
            }

            $instance_id = $this->app->getInstanceId();
            if ($instance_id !== null) {
                if ($this->account->instance->id !== $instance_id) {
                    throw new AdminNotFoundException(
                        sprintf(
                            Store::_('Incorrect instance for account ‘%d’.'),
                            $this->id
                        )
                    );
                }
            }
        }

        return $this->account;
    }

    // }}}
    // {{{ protected function getUiXml()

    protected function getUiXml()
    {
        return __DIR__ . '/details.xml';
    }

    // }}}

    // build phase
    // {{{ protected function buildInternal()

    protected function buildInternal()
    {
        parent::buildInternal();

        $this->buildAccountDetails();

        $toolbar = $this->ui->getWidget('details_toolbar');
        $toolbar->setToolLinkValues($this->account->id);
    }

    // }}}
    // {{{ protected function getAccountDetailsStore()

    protected function getAccountDetailsStore()
    {
        $account = $this->getAccount();
        $ds = new SwatDetailsStore($account);
        $ds->fullname = $account->getFullname();
        return $ds;
    }

    // }}}
    // {{{ protected function buildAccountDetails()

    protected function buildAccountDetails()
    {
        $ds = $this->getAccountDetailsStore();

        $details_frame = $this->ui->getWidget('details_frame');
        $details_frame->title = Site::_('Account');
        $details_frame->subtitle = $ds->fullname;

        $details_view = $this->ui->getWidget('details_view');

        $date_field = $details_view->getField('createdate');
        $date_renderer = $date_field->getRendererByPosition();
        $date_renderer->display_time_zone = $this->app->default_time_zone;

        $details_view->data = $ds;
    }

    // }}}
    // {{{ protected function getTableModel()

    protected function getTableModel(SwatView $view)
    {
        return null;
    }

    // }}}
    // {{{ protected function buildNavBar()

    protected function buildNavBar()
    {
        parent::buildNavBar();

        $fullname = $this->getAccount()->getFullname();
        $this->navbar->addEntry(new SwatNavBarEntry($fullname));
        $this->title = $fullname;
    }

    // }}}
}
