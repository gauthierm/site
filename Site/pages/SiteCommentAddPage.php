<?php

/**
 * Page for adding a comment asynchronously via a JSON request
 *
 * @package   Site
 * @copyright 2011-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteCommentAddPage extends SitePageDecorator
{
    // {{{ protected properties

    /**
     * @var SiteCommentable
     */
    protected $item;

    /**
     * @var SiteComment
     */
    protected $comment;

    /**
     * @var array
     */
    protected $response = array();

    // }}}
    // {{{ protected function createLayout()

    protected function createLayout()
    {
        return new SiteLayout($this->app, SiteJSONTemplate::class);
    }

    // }}}

    // init phase
    // {{{ public function init()

    public function init()
    {
        parent::init();
        $this->initItem();
        $this->initComment();
    }

    // }}}
    // {{{ abstract protected function initItem()

    abstract protected function initItem();

    // }}}
    // {{{ protected function initComment()

    protected function initComment()
    {
        $class_name = $this->getCommentClassName();
        $this->comment = new $class_name();
        $this->comment->setDatabase($this->app->db);
    }

    // }}}
    // {{{ protected function getCommentClassName()

    protected function getCommentClassName()
    {
        return SwatDBClassMap::get('SiteComment');
    }

    // }}}

    // process phase
    // {{{ public function process()

    public function process()
    {
        parent::process();

        try {
            switch ($this->getItemCommentStatus()) {
                case SiteCommentStatus::OPEN:
                case SiteCommentStatus::MODERATED:
                    $this->processComment();
                    break;
                default:
                    throw new SiteCommentJSONException(
                        Site::_('Commenting is not allowed for this item.')
                    );
                    break;
            }
        } catch (Exception $e) {
            if (!($e instanceof SiteCommentJSONException)) {
                $e->processAndContinue();
            }
            $this->handleException($e);
        }
    }

    // }}}
    // {{{ protected function processComment()

    protected function processComment()
    {
        $this->updateComment();
        $this->saveComment();
    }

    // }}}
    // {{{ protected function updateComment()

    protected function updateComment()
    {
        $now = new SwatDate();
        $now->toUTC();

        switch ($this->getItemCommentStatus()) {
            case SiteCommentStatus::OPEN:
                $status = SiteComment::STATUS_PUBLISHED;
                break;

            case SiteCommentStatus::MODERATED:
                $status = SiteComment::STATUS_PENDING;
                break;
        }

        $this->comment->fullname = $this->getFullname();
        $this->comment->link = $this->getLink();
        $this->comment->email = $this->getEmail();
        $this->comment->bodytext = $this->getBodytext();
        $this->comment->ip_address = $this->getIPAddress();
        $this->comment->user_agent = $this->getUserAgent();
        $this->comment->createdate = $now;
        $this->comment->status = $status;
    }

    // }}}
    // {{{ protected function saveComment()

    protected function saveComment()
    {
        if ($this->getParameter('remember_me', false)) {
            $this->saveCookie();
        } else {
            $this->deleteCookie();
        }

        $this->comment->spam = $this->isSpam();
        $this->item->addComment($this->comment);
        $this->item->save();
        $this->comment->postSave($this->app);
    }

    // }}}
    // {{{ protected function getParameter()

    protected function getParameter($name, $required = true)
    {
        $value = SiteApplication::initVar(
            $name,
            null,
            SiteApplication::VAR_POST
        );

        if ($value == '' && $required) {
            throw new SiteCommentJSONException(
                sprintf(Site::_('The %s field is required.'), $name)
            );
        }

        return $value;
    }

    // }}}
    // {{{ protected function getFullname()

    protected function getFullname()
    {
        return $this->getParameter('fullname', true);
    }

    // }}}
    // {{{ protected function getLink()

    protected function getLink()
    {
        return $this->getParameter('link', false);
    }

    // }}}
    // {{{ protected function getEmail()

    protected function getEmail()
    {
        return $this->getParameter('email', true);
    }

    // }}}
    // {{{ protected function getBodytext()

    protected function getBodytext()
    {
        return $this->getParameter('bodytext', true);
    }

    // }}}
    // {{{ protected function getIPAddress()

    protected function getIPAddress()
    {
        $ip_address = $this->app->getRemoteIP(255);
    }

    // }}}
    // {{{ protected function getUserAgent()

    protected function getUserAgent()
    {
        $user_agent = null;

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent = mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255);
        }

        return $user_agent;
    }

    // }}}
    // {{{ protected function isSpam()

    protected function isSpam()
    {
        $is_spam = false;

        if ($this->app->config->comment->akismet_key !== null) {
            $uri = $this->app->getBaseHref();
            try {
                $akismet = new Services_Akismet2(
                    $uri,
                    $this->app->config->comment->akismet_key
                );

                $akismet_comment = new Services_Akismet2_Comment(array(
                    'comment_author' => $this->comment->fullname,
                    'comment_author_email' => $this->comment->email,
                    'comment_author_url' => $this->comment->link,
                    'comment_content' => $this->comment->bodytext,
                    'permalink' => $this->getPermalink($comment)
                ));

                $is_spam = $akismet->isSpam($akismet_comment, true);
            } catch (Exception $e) {
            }
        }

        return $is_spam;
    }

    // }}}
    // {{{ protected function getItemCommentStatus()

    protected function getItemCommentStatus()
    {
        return $this->item->getCommentStatus();
    }

    // }}}
    // {{{ protected function saveCookie()

    protected function saveCookie()
    {
        if ($this->app->hasModule('SiteCookieModule')) {
            $cookie = $this->app->getModule('SiteCookieModule');
            $value = array(
                'fullname' => $this->getParameter('fullname', true),
                'link' => $this->getParameter('link', false),
                'email' => $this->getParameter('email', true)
            );

            $cookie->setCookie('comment_credentials', $value);
        }
    }

    // }}}
    // {{{ protected function deleteCookie()

    protected function deleteCookie()
    {
        if ($this->app->hasModule('SiteCookieModule')) {
            $cookie = $this->app->getModule('SiteCookieModule');
            $cookie->removeCookie('comment_credentials');
        }
    }

    // }}}
    // {{{ protected function handleException()

    protected function handleException(Exception $e)
    {
        $this->response = array(
            'status' => 'error',
            'message' => $e->getMessage(),
            'type' => get_class($e)
        );
    }

    // }}}

    // build phase
    // {{{ public function build()

    public function build()
    {
        parent::build();

        if (count($this->response) === 0) {
            // no error occurred, build success response
            $this->buildResponse();
        }

        $this->layout->startCapture('content');
        echo json_encode($this->response);
        $this->layout->endCapture();
    }

    // }}}
    // {{{ protected function buildResponse()

    protected function buildResponse()
    {
        $view = $this->getView();
        ob_start();
        $view->display($this->comment);
        $view_content = ob_get_clean();

        $this->response = array(
            'status' => 'success',
            'view' => $view_content,
            'id' => $this->comment->id,
            'comment_status' => $this->comment->status
        );
    }

    // }}}
    // {{{ protected function getView()

    protected function getView()
    {
        return SiteViewFactory::get($this->app, 'comment');
    }

    // }}}
}
