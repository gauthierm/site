<?php

/**
 * Report page for Ad
 *
 * @package   Site
 * @copyright 2006-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteAdIndex extends AdminIndex
{
    // init phase
    // {{{ protected function initInternal()

    protected function initInternal()
    {
        parent::initInternal();
        $this->ui->loadFromXML($this->getUiXml());
    }

    // }}}
    // {{{ protected function getUiXml()

    protected function getUiXml()
    {
        return __DIR__ . '/index.xml';
    }

    // }}}

    // process phase
    // {{{ protected function processActions()

    protected function processActions(SwatView $view, SwatActions $actions)
    {
        $num = count($view->getSelection());

        switch ($actions->selected->id) {
            case 'delete':
                $this->app->replacePage('Ad/Delete');
                $this->app->getPage()->setItems($view->getSelection());
                break;
        }
    }

    // }}}

    // build phase
    // {{{ protected function getTableModel()

    protected function getTableModel(SwatView $view)
    {
        $sql = sprintf(
            'select * from Ad
			order by %s',
            $this->getOrderByClause($view, 'createdate desc')
        );

        $ads = SwatDB::query(
            $this->app->db,
            $sql,
            SwatDBClassMap::get('SiteAdWrapper')
        );

        return $ads;
    }

    // }}}
}
