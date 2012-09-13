<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * \brief
 *      Represents a list of words that can be iterated.
 *
 * \note
 *      Wordlists are read-only objects. Any attempt to change
 *      their content will result in an exception being thrown.
 */
class       Erebot_Module_Wordlists_Wordlist
extends     Erebot_Module_Wordlists_Base
implements  Countable,
            ArrayAccess
{
    /// Name of the list (same as the filename with extension removed).
    const METADATA_NAME         = 'name';

    /// Path to the list's file.
    const METADATA_FILE         = 'file';

    /// Version of the list.
    const METADATA_VERSION      = 'version';

    /// A short description of the content of this list.
    const METADATA_DESCRIPTION  = 'description';

    /// A list with the names (and optionally emails) of this list's authors.
    const METADATA_AUTHORS      = 'author';

    /// The locale used by the words, using the ICU format (mandatory).
    const METADATA_LOCALE       = 'locale';

    /// Information on the license for this list of words.
    const METADATA_LICENCE      = 'license';

    /// Information on the license for this list of words.
    const METADATA_LICENSE      = 'license';

    /// A URL where more information/resources for this list may be found.
    const METADATA_URL          = 'url';

    /// A list of keywords associated with this list.
    const METADATA_KEYWORDS     = 'keyword';


    /// Metadata associated with this list.
    protected $_metadata = array(
        'version'       => NULL,
        'description'   => NULL,
        'author'        => array(),
        'locale'        => NULL,
        'license'       => NULL,
        'url'           => NULL,
        'keyword'       => array(),
    );

    /// Instance of Erebot_Module_Wordlists this wordlist is associated with.
    protected $_module;

    /// Name of this wordlist.
    protected $_name;

    /// Path to the file where this wordlists is stored.
    protected $_file;

    /// Database link as a PDO handle.
    protected $_db;

    /// A collator compatible with this wordlist.
    protected $_collator;

    /// An SQL query that returns the number of words in this wordlist.
    protected $_countQuery;

    /// An SQL query to test whether some word is present in the list or not.
    protected $_existsQuery;

    /// An SQL query to return the word at a given offset.
    protected $_getQuery;


    /**
     * Constructs a new wordlist.
     *
     * \param Erebot_Module_Wordlists $module
     *      A reference to the module that created this wordlist.
     *      It will be used to release the wordlist when it is destroyed.
     *
     * \param string $name
     *      Name of this wordlist.
     *
     * \param string $file
     *      Path to the file where this wordlist is stored.
     */
    public function __construct(
        Erebot_Module_Wordlists $module,
                                $name,
                                $file
    )
    {
        $this->_module  = $module;
        $this->_name    = $name;
        $this->_parseFile($file);
    }

    /**
     * Destroy the list, releasing any resource
     * it may have used.
     */
    public function __destruct()
    {
        // For PHARs, $this->_file points to a temporary file
        // containing a copy of the original database.
        if (!strncasecmp(__FILE__, 'phar://', 7)) {
            unlink($this->_file);
        }
    }

    /**
     * Parse the content of a SQLite file representing
     * a list of words.
     *
     * \param string $file
     *      The full path to the SQLite file containing
     *      the list of words to use.
     *
     * \throw Erebot_InvalidValueException
     *      The locale indicated in the file was invalid.
     */
    protected function _parseFile($file)
    {
        $this->_file    = $file;
        // When running as a PHAR, copy the SQLite database
        // to a temporary file. Required as the SQLite3 driver
        // does not support streams (and it can"t, see #55154).
        if (!strncasecmp(__FILE__, 'phar://', 7)) {
            $this->_file = tempnam(
                sys_get_temp_dir(),
                'Erebot_Module_Wordlists'
            );
            copy($file, $this->_file);
        }

        $this->_db      = new PDO('sqlite:' . $this->_file);
        $metadata       = $this->_db->query(
            'SELECT type, value '.
            'FROM metadata '.
            'ORDER BY type ASC, id ASC'
        );
        foreach ($metadata as $row) {
            if (!array_key_exists($row['type'], $this->_metadata))
                continue;
            if (is_array($this->_metadata[$row['type']]))
                $this->_metadata[$row['type']][] = $row['value'];
            else
                $this->_metadata[$row['type']] = $row['value'];
        }

        // Try to create a collator for the given locale.
        $this->_collator = new Collator(
            str_replace('-', '_', $this->_metadata['locale'])
        );
        // -127 = U_USING_DEFAULT_WARNING
        // -128 = U_USING_FALLBACK_WARNING.
        //    0 = U_ZERO_ERROR (no error).
        if (!in_array(intl_get_error_code(), array(-127, -128, 0))) {
            throw new Erebot_InvalidValueException(
                "Invalid locale (".$this->_metadata['locale']."): ".
                intl_get_error_message()
            );
        }
        // Ignore differences in case, accents and punctuation.
        $this->_collator->setStrength(Collator::PRIMARY);

        $this->_countQuery = $this->_db->prepare(
            'SELECT COUNT(1) '.
            'FROM words'
        );

        $this->_getQuery = $this->_db->prepare(
            'SELECT value '.
            'FROM words '.
            'ORDER BY sortkey ASC '.
            'LIMIT 1 OFFSET :offset'
        );

        $this->_existsQuery = $this->_db->prepare(
            'SELECT value '.
            'FROM words '.
            'WHERE sortkey = :key '.
            'LIMIT 1'
        );
    }

    /**
     * Releases the current wordlist.
     */
    public function free()
    {
        $this->_module->releaseList($this);
    }

    /**
     * Returns the Collator object associated
     * with this list.
     *
     * \retval Collator
     *      Collator for this list.
     */
    public function getCollator()
    {
        return $this->_collator;
    }

    /**
     * Returns the number of words in the list.
     *
     * \retval int
     *      Number of words in the list.
     */
    public function count()
    {
        $this->_countQuery->execute();
        $res = $this->_countQuery->fetchColumn();
        $this->_countQuery->closeCursor();
        return (int) $res;
    }

    /**
     * Real implementation of
     * Erebot_Module_Wordlists_Wordlist::findWord().
     *
     * \param string $word
     *      The word to look for.
     *
     * \note
     *      This method only exists to work around
     *      a bug in old versions of the intl extension
     *      where spurious warnings are raised when
     *      Collator::getSortKey() is called, even when
     *      run in a block where error_reporting = 0.
     *      See also PHP bug #62070.
     *
     * \warning
     *      Causes a crash on PHP < 5.3.0 with intl <= 3.0.0a1.
     *      Other combinations of PHP and intl are unaffected.
     */
    protected function _findWord($word)
    {
        /* getSortKey() is buggy in versions of intl up to 3.0.0a1 (included),
         * resulting in a crash on PHP 5.2.x with intl <= 3.0.0a1. For some
         * obscure reason though, the crash does not occur on PHP >= 5.3.0. */
        $key = $this->_collator->getSortKey($word);

        /* In intl > 3.0.0a1, the key now ends with a trailing NUL byte.
         * We remove it for backward-compatibility with older releases. */
        if (substr($key, -1) === "\0")
            $key = substr($key, 0, -1);

        $this->_existsQuery->execute(array(':key' => $key));
        $res = $this->_existsQuery->fetchColumn();
        $this->_existsQuery->closeCursor();
        if ($res === FALSE || $res === NULL)
            return NULL;
        return $res;
    }

    /**
     * Look for a word in the list.
     *
     * \param string $word
     *      The word to look for.
     *
     * \retval mixed
     *      If the given word was found, it is returned
     *      as it appears in the list (this may include
     *      case or accentuation variations).
     *      Otherwise, NULL is returned.
     *
     * \warning
     *      Causes a crash on PHP < 5.3.0 with intl <= 3.0.0a1.
     *      Other combinations of PHP and intl are unaffected.
     *
     * \see
     *      Erebot_Module_Wordlists_Wordlist::_findWord()
     */
    public function findWord($word)
    {
        return @$this->_findWord($word);
    }

    /**
     * Tests whether the given word exists in the list.
     *
     * \param string $word
     *      Some word whose existence will be tested.
     *
     * \retval bool
     *      TRUE if the given word is present in this list,
     *      FALSE otherwise.
     *
     * \warning
     *      Causes a crash on PHP < 5.3.0 with intl <= 3.0.0a1.
     *      Other combinations of PHP and intl are unaffected.
     *
     * \see
     *      Erebot_Module_Wordlists_Wordlist::_findWord()
     */
    public function offsetExists($word)
    {
        $res = (@$this->_findWord($word) !== NULL);
        return $res;
    }

    /**
     * Returns the word at the given offset.
     *
     * \param int $offset
     *      Offset of the word to retrieve.
     *
     * \retval string
     *      The word at the given $offset.
     *
     * \throw Erebot_InvalidValueException
     *      The given offset is not an integer.
     */
    public function offsetGet($offset)
    {
        if (!is_int($offset))
            throw new Erebot_InvalidValueException('An integer was expected');

        $this->_getQuery->execute(array(':offset' => $offset));
        $res = $this->_getQuery->fetchColumn();
        $this->_getQuery->closeCursor();

        if ($res == '' || $res === NULL || $res === FALSE)
            return NULL;
        return $res;
    }


    /**
     * Returns metadata associated with the list.
     *
     * \param string $type
     *      Type of metadata to return. See the constants
     *      named METADATA_* from this class for valid types.
     *
     * \retval mixed
     *      The requested metadata. This is a string for types
     *      that accept a single value (or NULL if the list does
     *      not provide any value) or an array for multi-valued
     *      types (an empty array may be returned in case the
     *      list does not provide any value).
     *
     * \throw Erebot_InvalidValueException
     *      The given metadata type is invalid.
     */
    public function getMetadata($type)
    {
        // Handle special cases here: name and path to file.
        if ($type == self::METADATA_NAME)
            return $this->_name;
        if ($type == self::METADATA_FILE)
            return $this->_file;

        if (!array_key_exists($type, $this->_metadata)) {
            throw new Erebot_InvalidValueException(
                'Invalid metadata type "' . $type . '"'
            );
        }
        return $this->_metadata[$type];
    }

}
