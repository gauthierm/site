<?php

/**
 * A one-time use token used to sign on using the API
 *
 * @package   Site
 * @copyright 2013-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteApiSignOnToken extends SwatDBDataObject
{
    // {{{ public properties

    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $ident;

    /**
     * @var string
     */
    public $token;

    /**
     * Create date
     *
     * @var SwatDate
     */
    public $createdate;

    // }}}
    // {{{ protected function init()

    protected function init()
    {
        parent::init();

        $this->table = 'ApiSignOnToken';
        $this->id_field = 'integer:id';

        $this->registerInternalProperty(
            'api_credential',
            SwatDBClassMap::get('SiteApiCredential')
        );

        $this->registerDateProperty('createdate');
    }

    // }}}
    // {{{ public function loadByIdent()

    public function loadByIdent($ident, SiteApiCredential $credential)
    {
        $this->checkDB();

        $row = null;

        if ($this->table !== null) {
            $sql = sprintf(
                'select * from %s
				where ident = %s and api_credential = %s',
                $this->table,
                $this->db->quote($ident, 'text'),
                $this->db->quote($credential->id, 'integer')
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
    // {{{ public function loadByIdentAndToken()

    public function loadByIdentAndToken(
        $ident,
        $token,
        SiteApiCredential $credential
    ) {
        $this->checkDB();

        $row = null;

        if ($this->table !== null) {
            $sql = sprintf(
                'select * from %s
				where ident = %s and token = %s and api_credential = %s',
                $this->table,
                $this->db->quote($ident, 'text'),
                $this->db->quote($token, 'text'),
                $this->db->quote($credential->id, 'integer')
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
}
