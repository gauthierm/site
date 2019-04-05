<?php

/**
 * An article in an web application
 *
 * Articles in a web application represent generic navigatable pages that
 * contain content.
 *
 * SiteArticle objects themselves may represent a tree structure by accessing
 * the {@link SiteArticle::$parent} property.
 *
 * @package   Site
 * @copyright 2006-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteArticle extends SwatDBDataObject
{
    // {{{ class constants

    /**
     * The maximum depth of articles in the article tree
     *
     * Objects that interact with articles may choose not to respect articles
     * with a depth greater than this value.
     *
     * The root article is the zero-th level article.
     */
    const MAX_DEPTH = 8;

    // }}}
    // {{{ public properties

    /**
     * Unique identifier
     *
     * @var integer
     */
    public $id;

    /**
     * User visible title
     *
     * @var string
     */
    public $title;

    /**
     * Optional HTML title
     *
     * If set, the article page HTML title uses this value. Otherwise, the
     * article page uses the article title from {@link SiteArticle::$title}.
     *
     * @var string
     */
    public $html_title;

    /**
     * User visible description
     *
     * @var string
     */
    public $description;

    /**
     * User visible content
     *
     * @var string
     */
    public $bodytext;

    /**
     * Create date
     *
     * @var SwatDate
     */
    public $createdate;

    /**
     * Modified date
     *
     * @var SwatDate
     */
    public $modified_date;

    /**
     * Order of display
     *
     * @var integer
     */
    public $displayorder;

    /**
     * Whether article can be loaded on the front-end (customer visible)
     *
     * @var boolean
     */
    public $enabled;

    /**
     * Whether article is listed in sub-article lists
     *
     * @var boolean
     */
    public $visible;

    /**
     * Weather article is included in search results
     *
     * @var boolean
     */
    public $searchable;

    /**
     * Short, textual identifer for this article
     *
     * The shortname must be unique among siblings and is intended for use
     * in URL's.
     *
     * @var string
     */
    public $shortname;

    // }}}
    // {{{ protected properties

    /**
     * @var array
     *
     * @see SiteArticle::getNavBarEntries()
     */
    protected $navbar_entries;

    // }}}
    // {{{ public function getNavBarEntries()

    /**
     * Gets the set of {@link SwatNavBarEntry} objects for this article
     *
     * @param string $link_prefix optional. A path to prepend to article links.
     *
     * @return array the set of SwatNavBarEntry objects for this article.
     */
    public function getNavBarEntries($link_prefix = '')
    {
        if ($this->navbar_entries === null) {
            $this->navbar_entries = array();

            $sql = sprintf(
                'select * from getArticleNavbar(%s)',
                $this->db->quote($this->id, 'integer')
            );

            $navbar_rows = SwatDB::query($this->db, $sql);

            $path = $link_prefix;
            foreach ($navbar_rows as $row) {
                if ($path == '') {
                    $path .= $row->shortname;
                } else {
                    $path .= '/' . $row->shortname;
                }

                $this->navbar_entries[] = new SwatNavBarEntry(
                    $row->title,
                    $path
                );
            }
        }

        return $this->navbar_entries;
    }

    // }}}
    // {{{ public function getVisibileSubArticles()

    /**
     * Get the sub-articles of this article that are both shown and enabled
     *
     * @return SiteArticleWrapper a recordset of sub-articles of the
     *                              specified article.
     */
    public function getVisibleSubArticles()
    {
        $sql = 'select Article.id, Article.title, Article.shortname,
				Article.description, Article.createdate
			from Article
			inner join VisibleArticleView on
				VisibleArticleView.id = Article.id
			where parent = %s
			order by displayorder, title';

        $sql = sprintf($sql, $this->db->quote($this->id, 'integer'));

        $wrapper = SwatDBClassMap::get('SiteArticleWrapper');
        return SwatDB::query($this->db, $sql, $wrapper);
    }

    // }}}
    // {{{ public function loadWithPath()

    /**
     * Loads an article from the database with a path
     *
     * @param string $path the path of the article in the article tree. Article
     *                      nodes are separated by a '/' character.
     * @param array $fields the article fields to load from the database. By
     *                       default, only the id and title are loaded. The
     *                       path pseudo-field is always populated from the
     *                       <code>$path</code> parameter.
     *
     * @return boolean true if an article was successfully loaded and false if
     *                  no article was found at the specified path.
     */
    public function loadWithPath($path, $fields = array('id', 'title'))
    {
        $this->checkDB();

        $found = false;

        $id_field = new SwatDBField($this->id_field, 'integer');
        foreach ($fields as &$field) {
            $field = $this->table . '.' . $field;
        }

        $sql = 'select %1$s from
				findArticle(%2$s)
			inner join %3$s on findArticle = %3$s.%4$s
			inner join VisibleArticleView on
				findArticle = VisibleArticleView.id';

        $sql = sprintf(
            $sql,
            implode(', ', $fields),
            $this->db->quote($path, 'text'),
            $this->table,
            $id_field->name
        );

        $row = SwatDB::queryRow($this->db, $sql);
        if ($row !== null) {
            $this->initFromRow($row);
            $this->setInternalValue('path', $path);
            $this->generatePropertyHashes();
            $found = true;
        }

        return $found;
    }

    // }}}
    // {{{ public function loadByShortname()

    /**
     * Loads an article from its shortname
     *
     * @param string $shortname the shortname of the article to load.
     *
     * @return boolean true if the loading of this article was successful and
     *                  false if the article with the given shortname doesn't
     *                  exist.
     */
    public function loadByShortname($shortname)
    {
        $this->checkDB();

        $row = null;

        if ($this->table !== null) {
            $sql = sprintf(
                'select * from %s where shortname = %s',
                $this->table,
                $this->db->quote($shortname)
            );

            $rs = SwatDB::query($this->db, $sql, null);
            $row = $rs->fetchRow(MDB2_FETCHMODE_ASSOC);
        }

        if ($row === null) {
            return false;
        }

        $this->initFromRow($row);
        $this->generatePropertyHashes();

        return true;
    }

    // }}}
    // {{{ protected function init()

    protected function init()
    {
        $this->registerInternalProperty(
            'parent',
            SwatDBClassMap::get('SiteArticle')
        );

        $this->registerDateProperty('createdate');
        $this->registerDateProperty('modified_date');

        $this->table = 'Article';
        $this->id_field = 'integer:id';
    }

    // }}}
    // {{{ protected function getSerializableSubDataObjects()

    protected function getSerializableSubDataObjects()
    {
        return array_merge(parent::getSerializableSubDataObjects(), array(
            'parent',
            'path',
            'sub_articles'
        ));
    }

    // }}}

    // loader methods
    // {{{ protected function loadPath()

    /**
     * Loads the URL fragment of this article
     *
     * If the path was part of the initial query to load this article, that
     * value is returned. Otherwise, a separate query gets the path of this
     * article. If you are calling this method frequently during a single
     * request, it is more efficient to include the path in the initial
     * article query.
     */
    protected function loadPath()
    {
        $path = '';

        if (
            $this->hasInternalValue('path') &&
            $this->getInternameValue('path') !== null
        ) {
            $path = $this->getInternalValue('path');
        } else {
            $sql = sprintf(
                'select getArticlePath(%s)',
                $this->db->quote($this->id, 'integer')
            );

            $path = SwatDB::queryOne($this->db, $sql);
        }

        return $path;
    }

    // }}}
    // {{{ protected function loadSubArticles()

    /**
     * Loads the sub-articles of this article
     *
     * @return SiteArticleWrapper a recordset of sub-articles of the
     *                              specified article.
     */
    protected function loadSubArticles()
    {
        $sql = 'select id, title, shortname, description,
				createdate
			from Article
			where parent = %s and id in
			(select id from VisibleArticleView)
			order by displayorder, title';

        $sql = sprintf($sql, $this->db->quote($this->id, 'integer'));

        $wrapper = SwatDBClassMap::get('SiteArticleWrapper');
        return SwatDB::query($this->db, $sql, $wrapper);
    }

    // }}}
}
