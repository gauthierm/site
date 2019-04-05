<?php

/**
 * A fulltext search engine that uses NateGoSearch
 *
 * @package   Site
 * @copyright 2007-2016 silverorange
 */
class SiteNateGoFulltextSearchEngine extends SwatObject implements
    SiteFulltextSearchEngine
{
    // {{{ protected properties

    /**
     * The database
     *
     * @var MDB2_Driver_Common
     */
    protected $db;

    /**
     * The locale to use for spell checking
     *
     * @var string
     */
    protected $locale;

    /**
     * The document types to search
     *
     * @var array
     */
    protected $types = array();

    /**
     * An array of popular keywords passed to the query object.
     *
     * @var array
     */
    protected $popular_keywords = array();

    // }}}
    // {{{ public function __construct()

    /**
     * Creates a new nate-go fulltext search engine
     *
     * @param MDB2_Driver_Common $db
     * @param string $locale optional.
     */
    public function __construct($db, $locale = null)
    {
        $this->db = $db;
        if ($locale === null) {
            $this->locale = setlocale(LC_ALL, '0');
        } else {
            $this->locale = $locale;
        }
    }

    // }}}
    // {{{ public function setTypes()

    /**
     * Sets the document types to search
     *
     * @param array $types
     */
    public function setTypes(array $types)
    {
        $this->types = $types;
    }

    // }}}
    // {{{ public function setPopularKeywords()

    /**
     * Set the popular keywords for this search engine.
     *
     * @param array $popular_keywords the array of popular keywords to add.
     */
    public function setPopularKeywords(array $popular_keywords)
    {
        $this->popular_keywords = $popular_keywords;
    }

    // }}}
    // {{{ public function search()

    /**
     * Perform a fulltext search and return the result
     *
     * @param string $keywords the keywords to search with.
     *
     * @return SiteFulltextSearchResult
     */
    public function search($keywords)
    {
        $spell_checker = new NateGoSearchPSpellSpellChecker(
            $this->locale,
            '',
            '',
            $this->getCustomWordList()
        );

        $query = new NateGoSearchQuery($this->db);
        $query->addBlockedWords(NateGoSearchQuery::getDefaultBlockedWords());
        $query->addPopularWords(
            NateGoSearchQuery::getSearchHistoryPopularWords($this->db)
        );
        $query->addPopularWords($this->popular_keywords);
        $query->setSpellChecker($spell_checker);

        foreach ($this->types as $type) {
            $query->addDocumentType($type);
        }

        $result = $query->query($keywords);

        return new SiteNateGoFulltextSearchResult($this->db, $result);
    }

    // }}}
    // {{{ protected function getCustomWordList()

    /**
     * Get the custom word list
     *
     * Get the custom word list that is used by this fulltext search engine.
     *
     * @return string the path to the custom word list
     */
    protected function getCustomWordList()
    {
        return './../system/search/custom-wordlist.pws';
    }

    // }}}
}
