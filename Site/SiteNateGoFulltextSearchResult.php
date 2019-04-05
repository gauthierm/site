<?php

/**
 * A fulltext search result that uses NateGoSearch
 *
 * @package   Site
 * @copyright 2007-2016 silverorange
 */
class SiteNateGoFulltextSearchResult extends SwatObject implements
    SiteFulltextSearchResult
{
    // {{{ protected properties

    /**
     * The database
     *
     * @var MDB2_Driver_Common
     */
    protected $db;

    /**
     * The nate-go result object
     *
     * @var NateGoSearchResult
     */
    protected $nate_go_result;

    // }}}
    // {{{ public function __construct()

    /**
     * Creates a new nate-go fulltext search result
     *
     * @param MDB2_Driver_Common $db the database.
     * @param NateGoSearchResult $result a NateGoSearchResult object.
     */
    public function __construct(
        MDB2_Driver_Common $db,
        NateGoSearchResult $result
    ) {
        $this->db = $db;
        $this->nate_go_result = $result;
    }

    // }}}
    // {{{ public function getJoinClause()

    public function getJoinClause($id_field_name, $type)
    {
        $type = $this->nate_go_result->getDocumentType($type);

        $clause = sprintf(
            'inner join %1$s on
			%1$s.document_id = %2$s and
			%1$s.unique_id = %3$s and %1$s.document_type = %4$s',
            $this->nate_go_result->getResultTable(),
            $id_field_name,
            $this->db->quote($this->nate_go_result->getUniqueId(), 'text'),
            $this->db->quote($type, 'integer')
        );

        return $clause;
    }

    // }}}
    // {{{ public function getOrderByClause()

    public function getOrderByClause($default_clause)
    {
        $terms = $this->getOrderByTerms();

        $clause = 'order by ' . implode(',', $terms);

        if ($default_clause != '') {
            $clause .= ', ' . $default_clause;
        }

        return $clause;
    }

    // }}}
    // {{{ public function getOrderByTerms()

    public function getOrderByTerms()
    {
        $terms = array();
        $table = $this->nate_go_result->getResultTable();

        $terms[] = sprintf('%s.displayorder1', $table);
        $terms[] = sprintf('%s.displayorder2', $table);

        return $terms;
    }

    // }}}
    // {{{ public function getMisspellings()

    public function getMisspellings()
    {
        return $this->nate_go_result->getMisspellings();
    }

    // }}}
    // {{{ public function &getSearchedWords()

    /**
     * Gets words that were entered and were searched for
     *
     * @return array words that were entered and were searched for.
     */
    public function &getSearchedWords()
    {
        return $this->nate_go_result->getSearchedWords();
    }

    // }}}
    // {{{ public function saveHistory()

    /**
     * Saves this search result for search statistics and tracking
     */
    public function saveHistory()
    {
        $this->nate_go_result->saveHistory();
    }

    // }}}
    // {{{ public function getResultTable()

    public function getResultTable()
    {
        return $this->nate_go_result->getResultTable();
    }

    // }}}
    // {{{ public function getUniqueId()

    public function getUniqueId()
    {
        return $this->nate_go_result->getUniqueId();
    }

    // }}}
}
