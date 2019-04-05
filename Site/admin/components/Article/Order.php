<?php

/**
 * Order page for Articles
 *
 * @package   Site
 * @copyright 2005-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteArticleOrder extends AdminDBOrder
{
    // {{{ protected properties

    protected $parent;

    // }}}
    // {{{ protected function getWhereClause()

    protected function getWhereClause()
    {
        $where_clause = sprintf(
            'parent %s %s',
            SwatDB::equalityOperator($this->parent),
            $this->app->db->quote($this->parent, 'integer')
        );

        return $where_clause;
    }

    // }}}

    // init phase
    // {{{ protected function initInternal()

    protected function initInternal()
    {
        parent::initInternal();

        $this->parent = SiteApplication::initVar('parent');
        $form = $this->ui->getWidget('order_form');
        $form->addHiddenField('parent', $this->parent);
    }

    // }}}

    // process phase
    // {{{ protected function saveIndex()

    protected function saveIndex($id, $index)
    {
        SwatDB::updateColumn(
            $this->app->db,
            'Article',
            'integer:displayorder',
            $index,
            'integer:id',
            array($id),
            $this->getWhereClause()
        );

        if (isset($this->app->memcache)) {
            $this->app->memcache->flushNs('article');
        }
    }

    // }}}

    // build phase
    // {{{ protected function buildInternal()
    protected function buildInternal()
    {
        parent::buildInternal();

        $frame = $this->ui->getWidget('order_frame');
        $frame->title = Site::_('Order Articles');
    }

    // }}}
    // {{{ protected function loadData()

    protected function loadData()
    {
        $order_widget = $this->ui->getWidget('order');
        $order_widget->addOptionsByArray(
            SwatDB::getOptionArray(
                $this->app->db,
                'Article',
                'title',
                'id',
                'displayorder, title',
                $this->getWhereClause()
            )
        );

        $sql =
            'select sum(displayorder) from Article where ' .
            $this->getWhereClause();

        $sum = SwatDB::queryOne($this->app->db, $sql, 'integer');
        $options_list = $this->ui->getWidget('options');
        $options_list->value = $sum == 0 ? 'auto' : 'custom';
    }

    // }}}
    // {{{ protected function buildNavBar()

    protected function buildNavBar()
    {
        $this->navbar->popEntry();
        $this->navbar->addEntry(
            new SwatNavBarEntry(Site::_('Articles'), 'Article')
        );

        if ($this->parent !== null) {
            $navbar_rs = SwatDB::executeStoredProc(
                $this->app->db,
                'getArticleNavBar',
                array($this->parent)
            );

            foreach ($navbar_rs as $elem) {
                $this->navbar->addEntry(
                    new SwatNavBarEntry(
                        $elem->title,
                        'Article/Index?id=' . $elem->id
                    )
                );
            }
        }

        parent::buildNavBar();
    }

    // }}}
}
