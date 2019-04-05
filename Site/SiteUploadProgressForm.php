<?php

/**
 * A form with a upload progress bar
 *
 * @package   Site
 * @copyright 2008-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @todo      test JavaScript progressive-enhancement support
 */
class SiteUploadProgressForm extends SwatForm
{
    // {{{ public properties

    public $upload_status_server = 'xml-rpc/upload-status';

    // }}}
    // {{{ public function __construct()

    /**
     * Creates a new progress bar
     *
     * @param string $id a non-visible unique id for this widget.
     *
     * @see SwatWidget::__construct()
     */
    public function __construct($id = null)
    {
        parent::__construct($id);

        $this->requires_id = true;

        $yui = new SwatYUI(array('event', 'animation'));
        $this->html_head_entry_set->addEntrySet($yui->getHtmlHeadEntrySet());

        $ajax = new XML_RPCAjax();
        $this->html_head_entry_set->addEntrySet($ajax->getHtmlHeadEntrySet());

        $this->addJavaScript(
            'packages/site/javascript/site-upload-progress-bar.js'
        );
    }

    // }}}
    // {{{ public funciton init()

    public function init()
    {
        parent::init();

        $added = false;

        // if the last child of this form is a footer form field, append
        // the progress bar to the form field
        if (count($this->children) > 0) {
            $last_child = end($this->children);
            if ($last_child instanceof SwatFooterFormField) {
                $container = $this->getCompositeWidget('container');
                $container->parent = null;
                $last_child->add($container);
                $added = true;
            }
        }

        if (!$added) {
            $container = $this->getCompositeWidget('container');
            $container->parent = null;
            $this->add($container);
        }
    }

    // }}}
    // {{{ protected function displayChildren()

    protected function displayChildren()
    {
        $hidden_input_tag = new SwatHtmlTag('input');
        $hidden_input_tag->type = 'hidden';
        $hidden_input_tag->id = $this->id . '_identifier';
        $hidden_input_tag->name = 'UPLOAD_IDENTIFIER';
        $hidden_input_tag->value = $this->id . '_' . uniqid();
        $hidden_input_tag->display();

        parent::displayChildren();
    }

    // }}}
    // {{{ protected function getInlineJavaScript()

    protected function getInlineJavaScript()
    {
        $javascript = parent::getInlineJavaScript();
        $javascript .= $this->getInlineJavaScriptTranslations();

        $javascript .= sprintf(
            "%s_obj = new SiteUploadProgressClient('%s', '%s', %s);",
            $this->id,
            $this->id,
            $this->upload_status_server,
            $this->id . '_progress_bar_obj'
        );

        return $javascript;
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
        return sprintf(
            "SiteUploadProgressClient.progress_unknown_text = '%s';\n" .
                "SiteUploadProgressClient.hours_text = '%s';\n" .
                "SiteUploadProgressClient.minutes_text = '%s';\n" .
                "SiteUploadProgressClient.seconds_left_text = '%s';\n",
            Site::_('uploading…'),
            Site::_('hours'),
            Site::_('minutes'),
            Site::_('seconds left')
        );
    }

    // }}}
    // {{{ protected function createCompositeWidgets()

    /**
     * Creates all internal widgets required for this uploader
     */
    protected function createCompositeWidgets()
    {
        $progress_bar = new SwatProgressBar($this->id . '_progress_bar');
        $progress_bar->length = '100%';

        $progress_container = new SwatDisplayableContainer();
        $progress_container->classes = array(
            'site-upload-progress-bar',
            'swat-hidden'
        );
        $progress_container->add($progress_bar);

        $container = new SwatDisplayableContainer();
        $container->id = $this->id . '_container';
        $container->classes = array('site-upload-progress-bar-container');
        $container->add($progress_container);

        $this->addCompositeWidget($container, 'container');
    }

    // }}}
}
