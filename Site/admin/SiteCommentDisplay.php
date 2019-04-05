<?php

/**
 * Displays a comment with optional buttons to edit, set published status
 * delete and mark as spam
 *
 * @package   Site
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteCommentDisplay extends SwatControl
{
    // {{{ public properties

    /**
     * @var boolean
     */
    public $show_controls = true;

    /**
     * @var boolean
     */
    public $show_actions_button = true;

    /**
     * @var string
     */
    public $comment_component = null;

    // }}}
    // {{{ protected properties

    /**
     * @var SiteComment
     *
     * @see SiteCommentDisplay::setComment()
     */
    protected $comment;

    /**
     * @var SiteApplication
     *
     * @see SiteCommentDisplay::setApplication()
     */
    protected $app;

    /**
     * @var SiteCommentView
     *
     * @see SiteCommentDisplay::getView()
     */
    protected $view;

    // }}}
    // {{{ public function __construct()

    public function __construct($id = null)
    {
        parent::__construct($id);

        $this->html_head_entry_set->addEntrySet(
            XML_RPCAjax::getHtmlHeadEntrySet()
        );

        $yui = new SwatYUI(array('dom', 'event', 'animation'));
        $this->html_head_entry_set->addEntrySet($yui->getHtmlHeadEntrySet());

        $this->addStyleSheet(
            'packages/site/admin/styles/site-comment-display.css'
        );

        $this->addJavaScript(
            'packages/site/admin/javascript/site-comment-display.js'
        );
    }

    // }}}
    // {{{ public function setComment()

    public function setComment(SiteComment $comment)
    {
        $this->comment = $comment;
    }

    // }}}
    // {{{ public function setApplication()

    public function setApplication(SiteApplication $app)
    {
        $this->app = $app;
    }

    // }}}
    // {{{ public function display()

    public function display()
    {
        if (!$this->visible) {
            return;
        }

        if ($this->comment === null) {
            return;
        }

        if ($this->app === null) {
            return;
        }

        parent::display();

        $this->displayRow();

        if ($this->show_controls) {
            Swat::displayInlineJavaScript($this->getInlineJavaScript());
        }
    }

    // }}}
    // {{{ public function setView()

    public function setView(SiteCommentView $view)
    {
        $this->view = $view;
    }

    // }}}
    // {{{ abstract protected function displayHeader()

    abstract protected function displayHeader();

    // }}}
    // {{{ protected function displayRow()

    protected function displayRow()
    {
        $container_div = new SwatHtmlTag('div');
        $container_div->class = $this->getCSSClassString();
        $container_div->id = $this->id;
        $container_div->open();

        $animation_container = new SwatHtmlTag('div');
        $animation_container->class = 'site-comment-display-content';
        $animation_container->open();

        $this->displayControls();
        $this->displayHeader();

        $view = $this->getView();
        $view->display($this->comment);

        $animation_container->close();
        $container_div->close();
    }

    // }}}
    // {{{ protected function displayControls()

    protected function displayControls()
    {
        if ($this->show_controls) {
            $controls_div = new SwatHtmlTag('div');
            $controls_div->id = $this->id . '_controls';
            $controls_div->class = 'site-comment-display-controls';
            $controls_div->open();
            $controls_div->close();
        }
    }

    // }}}
    // {{{ protected function displayStatusSpan()

    protected function displayStatusSpan()
    {
        $status_span = new SwatHtmlTag('span');
        $status_span->id = $this->id . '_status';
        $status_span->class = 'site-comment-display-status';
        $status_span->open();

        if ($this->comment->spam) {
            echo ' - ', Site::_('Spam');
        } else {
            switch ($this->comment->status) {
                case SiteComment::STATUS_UNPUBLISHED:
                    echo ' - ', Site::_('Unpublished');
                    break;

                case SiteComment::STATUS_PENDING:
                    echo ' - ', Site::_('Pending');
                    break;
            }
        }

        $status_span->close();
    }

    // }}}
    // {{{ protected function getView()

    protected function getView()
    {
        if ($this->view === null && $this->app !== null) {
            $this->view = SiteViewFactory::get($this->app, 'comment');
            $this->view->setPartMode('bodytext', SiteView::MODE_SUMMARY);
            $this->view->setPartMode('permalink', SiteView::MODE_ALL, false);
            $this->view->setPartMode('author', SiteView::MODE_ALL, false);
            $this->view->setPartMode('link', SiteView::MODE_ALL, false);
        }

        return $this->view;
    }

    // }}}
    // {{{ protected function getCSSClassNames()

    /**
     * Gets the array of CSS classes that are applied to this comment display
     *
     * @return array the array of CSS classes that are applied to this comment
     *                display.
     */
    protected function getCSSClassNames()
    {
        $classes = array('site-comment-display');
        $classes = array_merge($classes, parent::getCSSClassNames());
        $classes[] = $this->getVisibilityCssClassName();
        return $classes;
    }

    // }}}
    // {{{ protected function getVisibilityCssClassName()

    protected function getVisibilityCssClassName()
    {
        if ($this->comment->spam) {
            $class = 'site-comment-red';
        } else {
            switch ($this->comment->status) {
                case SiteComment::STATUS_UNPUBLISHED:
                    $class = 'site-comment-red';
                    break;

                case SiteComment::STATUS_PENDING:
                    $class = 'site-comment-yellow';
                    break;

                case SiteComment::STATUS_PUBLISHED:
                default:
                    $class = 'site-comment-green';
                    break;
            }
        }

        return $class;
    }

    // }}}
    // {{{ protected function getInlineJavaScript()

    /**
     * Gets the inline JavaScript required by this control
     *
     * @return string the inline JavaScript required by this control.
     */
    protected function getInlineJavaScript()
    {
        static $shown = false;

        if (!$shown) {
            $javascript = $this->getInlineJavaScriptTranslations();

            $javascript .= sprintf(
                "SiteCommentDisplay.comment_component = %s;\n",
                SwatString::quoteJavaScriptString($this->getCommentComponent())
            );

            $shown = true;
        } else {
            $javascript = '';
        }

        $spam = $this->comment->spam ? 'true' : 'false';
        $status = $this->comment->status;
        $show_actions_button = $this->show_actions_button ? 'true' : 'false';
        $edit_uri = SwatString::quoteJavaScriptString($this->getEditUri());

        $javascript .= sprintf(
            'var %s_obj = new %s(%s, %s, %s, %s, %s, %s);',
            $this->id,
            $this->getJavaScriptClassName(),
            SwatString::quoteJavaScriptString($this->id),
            SwatString::quoteJavaScriptString($this->comment->id),
            $status,
            $spam,
            $show_actions_button,
            $edit_uri
        );

        return $javascript;
    }

    // }}}
    // {{{ protected function getJavaScriptClassName()

    protected function getJavaScriptClassName()
    {
        return 'SiteCommentDisplay';
    }

    // }}}
    // {{{ protected function getEditUri()

    protected function getEditUri()
    {
        return sprintf(
            '%s/Edit?id=%s',
            $this->getCommentComponent(),
            $this->comment->id
        );
    }

    // }}}
    // {{{ protected function getCommentComponent()

    protected function getCommentComponent()
    {
        if ($this->comment_component === null) {
            return $this->app->getPage()->getComponentName();
        } else {
            return $this->comment_component;
        }
    }

    // }}}
    // {{{ protected function getInlineJavaScriptTranslations()

    /**
     * Gets translatable string resources for the JavaScript object for
     * this widget
     *
     * @return string translatable JavaScript string resources for this widget.
     */
    protected function getInlineJavaScriptTranslations()
    {
        $edit_text = SwatString::quoteJavaScriptString(Site::_('Edit'));
        $approve_text = SwatString::quoteJavaScriptString(Site::_('Approve'));
        $deny_text = SwatString::quoteJavaScriptString(Site::_('Deny'));
        $publish_text = SwatString::quoteJavaScriptString(Site::_('Publish'));
        $spam_text = SwatString::quoteJavaScriptString(Site::_('Spam'));
        $delete_text = SwatString::quoteJavaScriptString(Site::_('Delete'));
        $cancel_text = SwatString::quoteJavaScriptString(Site::_('Cancel'));

        $not_spam_text = SwatString::quoteJavaScriptString(Site::_('Not Spam'));

        $unpublish_text = SwatString::quoteJavaScriptString(
            Site::_('Unpublish')
        );

        $status_spam_text = SwatString::quoteJavaScriptString(Site::_('Spam'));

        $status_pending_text = SwatString::quoteJavaScriptString(
            Site::_('Pending')
        );

        $status_unpublished_text = SwatString::quoteJavaScriptString(
            Site::_('Unpublished')
        );

        $delete_confirmation_text = SwatString::quoteJavaScriptString(
            Site::_('Delete comment?')
        );

        return "SiteCommentDisplay.edit_text   = {$edit_text};\n" .
            "SiteCommentDisplay.approve_text   = {$approve_text};\n" .
            "SiteCommentDisplay.deny_text      = {$deny_text};\n" .
            "SiteCommentDisplay.publish_text   = {$publish_text};\n" .
            "SiteCommentDisplay.unpublish_text = {$unpublish_text};\n" .
            "SiteCommentDisplay.spam_text      = {$spam_text};\n" .
            "SiteCommentDisplay.not_spam_text  = {$not_spam_text};\n" .
            "SiteCommentDisplay.delete_text    = {$delete_text};\n" .
            "SiteCommentDisplay.cancel_text    = {$cancel_text};\n\n" .
            'SiteCommentDisplay.status_spam_text        = ' .
            "{$status_spam_text};\n" .
            'SiteCommentDisplay.status_pending_text     = ' .
            "{$status_pending_text};\n" .
            'SiteCommentDisplay.status_unpublished_text = ' .
            "{$status_unpublished_text};\n\n" .
            'SiteCommentDisplay.delete_confirmation_text = ' .
            "{$delete_confirmation_text};\n\n";
    }

    // }}}
}
