<?php

/**
 * Performs actions on comments via AJAX
 *
 * @package   Site
 * @copyright 2009-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteCommentAjaxServer extends SiteXMLRPCServer
{
    // {{{ public function spam()

    /**
     * Marks a comment as spam
     *
     * @param integer $comment_id the id of the comment to mark as spam.
     *
     * @return boolean true.
     */
    public function spam($comment_id)
    {
        $comment = $this->getComment($comment_id);

        if ($comment !== null) {
            if (!$comment->spam) {
                // submit spam to akismet
                if ($this->app->config->comment->akismet_key !== null) {
                    $uri = $this->app->getFrontendBaseHref();
                    try {
                        $akismet = new Services_Akismet2(
                            $uri,
                            $this->app->config->comment->akismet_key
                        );

                        $akismet_comment = $this->getAkismetComment($comment);

                        $akismet->submitSpam($akismet_comment);
                    } catch (Exception $e) {
                    }
                }

                $comment->spam = true;
                $comment->save();
                $comment->postSave($this->app);
            }
        }

        return true;
    }

    // }}}
    // {{{ public function notSpam()

    /**
     * Marks a comment as not spam
     *
     * @param integer $comment_id the id of the comment to mark as not spam.
     *
     * @return boolean true.
     */
    public function notSpam($comment_id)
    {
        $comment = $this->getComment($comment_id);
        if ($comment !== null) {
            if ($comment->spam) {
                // submit false positive to akismet
                if ($this->app->config->comment->akismet_key !== null) {
                    $uri = $this->app->getFrontendBaseHref();
                    try {
                        $akismet = new Services_Akismet2(
                            $uri,
                            $this->app->config->comment->akismet_key
                        );

                        $akismet_comment = $this->getAkismetComment($comment);

                        $akismet->submitFalsePositive($akismet_comment);
                    } catch (Exception $e) {
                    }
                }

                $comment->spam = false;
                $comment->save();
                $comment->postSave($this->app);
            }
        }

        return true;
    }

    // }}}
    // {{{ public function publish()

    /**
     * Publishes a comment
     *
     * @param integer $comment_id the id of the comment to publish.
     *
     * @return boolean true.
     */
    public function publish($comment_id)
    {
        $comment = $this->getComment($comment_id);
        if ($comment !== null) {
            if ($comment->status !== SiteComment::STATUS_PUBLISHED) {
                $comment->status = SiteComment::STATUS_PUBLISHED;
                $comment->save();
                $comment->postSave($this->app);
            }
        }

        return true;
    }

    // }}}
    // {{{ public function unpublish()

    /**
     * Unpublishes a comment
     *
     * @param integer $comment_id the id of the comment to unpublish.
     *
     * @return boolean true.
     */
    public function unpublish($comment_id)
    {
        $comment = $this->getComment($comment_id);
        if ($comment !== null) {
            if ($comment->status !== SiteComment::STATUS_UNPUBLISHED) {
                $comment->status = SiteComment::STATUS_UNPUBLISHED;
                $comment->save();
                $comment->postSave($this->app);
            }
        }

        return true;
    }

    // }}}
    // {{{ public function delete()

    /**
     * Deletes a comment
     *
     * @param integer $comment_id the id of the comment to delete.
     *
     * @return boolean true.
     */
    public function delete($comment_id)
    {
        $comment = $this->getComment($comment_id);
        if ($comment !== null) {
            $comment->delete();
            $comment->clearCache($this->app);
        }

        return true;
    }

    // }}}
    // {{{ abstract protected function getPermalink()

    abstract protected function getPermalink(SiteComment $comment);

    // }}}
    // {{{ protected function getAkismetComment()

    protected function getAkismetComment(SiteComment $comment)
    {
        return new Services_Akismet2_Comment(array(
            'comment_author' => $comment->fullname,
            'comment_author_email' => $comment->email,
            'comment_author_url' => $comment->link,
            'comment_content' => $comment->bodytext,
            'permalink' => $this->getPermalink($comment),
            'user_ip' => $comment->ip_address,
            'user_agent' => $comment->user_agent
        ));
    }

    // }}}
    // {{{ protected function getComment()

    protected function getComment($comment_id)
    {
        $comment_class = SwatDBClassMap::get('SiteComment');
        $comment = new $comment_class();
        $comment->setDatabase($this->app->db);
        if ($comment->load($comment_id, $this->app->getInstance())) {
            return $comment;
        } else {
            return null;
        }
    }

    // }}}
}
