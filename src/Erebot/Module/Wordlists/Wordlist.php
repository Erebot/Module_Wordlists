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
implements  Countable,
            ArrayAccess
{
    /// Pattern used to recognize (composed) "words".
    const WORD_FILTER = '@^
        [\\p{N}\\p{L}\\-\\.\\(\\)_\']+          # A "word", ie. a sequence of
                                                # Unicode letters/numbers plus
                                                # some additional characters.
        (?:\\ [\\p{N}\\p{L}\\-\\.\\(\\)_\']+)?  # Another such word.
        $@ux';

    /// Name of the list (same as the filename with extension removed).
    const METADATA_NAME         = 'name';

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
        'name'          => NULL,
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
        $this->_file    = $file;
        $this->_parseFile($file);
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
        $this->_db      = new PDO("sqlite:$file");

        foreach ($this->_db->query(
                    'SELECT type, value '.
                    'FROM metadata '.
                    'ORDER BY type ASC, id ASC'
                ) as $row) {
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
     * Returns the name of the list.
     *
     * \retval string
     *      Name of the list.
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Returns the path to the file where the list
     * is stored.
     *
     * \retval string
     *      Path to the file containing the list.
     */
    public function getFile()
    {
        return $this->_file;
    }

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
        return $res;
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
     */
    protected function _findWord($word)
    {
        $key = $this->_collator->getSortKey($word);
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
     * Sets the word at the given offset to a new value.
     *
     * \param int $offset
     *      Offset to set.
     *
     * \param string $value
     *      New word for that offset.
     *
     * \throws Erebot_NotImplementedException
     *      Wordlists are read-only objects.
     *
     * \warning
     *      Actually, this method always throws an exception
     *      because wordlists are read-only objects.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function offsetSet($offset, $value)
    {
        throw new Erebot_NotImplementedException();
    }

    /**
     * Removes the word at the given offset from the wordlist.
     *
     * \param int $offset
     *      Offset to unset.
     *
     * \throws Erebot_NotImplementedException
     *      Wordlists are read-only objects.
     *
     * \warning
     *      Actually, this method always throws an exception
     *      because wordlists are read-only objects.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function offsetUnset($offset)
    {
        throw new Erebot_NotImplementedException();
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
     *      not provide any value) or an array of multi-valued
     *      types (an empty array may be returned in case the
     *      list does not provide any value).
     *
     * \throw Erebot_InvalidValueException
     *      The given metadata type is invalid.
     */
    public function getMetadata($type)
    {
        if (!array_key_exists($type, $this->_metadata)) {
            throw new Erebot_InvalidValueException(
                'Invalid metadata type "' . $type . '"'
            );
        }
        return $this->_metadata[$type];
    }

     /**
     * Filters non-words out.
     *
     * Only texts that passed this filtering step
     * are considered as propositions for the game.
     *
     * \param string $word
     *      A possible "word" to test.
     *
     * \retval bool
     *      TRUE if the given $word really is a word,
     *      FALSE otherwise.
     *
     * \note
     *      This method uses a rather broad definition
     *      of what is a word. In particular, it accepts
     *      sequences of (alphanumeric and other) characters
     *      separated by a single space (eg. "Fo'o. B4-r_").
     */
    static public function isWord($word)
    {
        if (!is_string($word))
            return FALSE;
        return (bool) preg_match(self::WORD_FILTER, $word);
    }
}
