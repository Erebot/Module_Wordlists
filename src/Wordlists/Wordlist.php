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

namespace Erebot\Module\Wordlists;

/**
 * \brief
 *      Represents a list of words that can be iterated.
 *
 * \note
 *      Wordlists are read-only objects. Any attempt to change
 *      their content will result in an exception being thrown.
 */
class Wordlist extends \Erebot\Module\Wordlists\Base implements \Countable, \ArrayAccess
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
    protected $metadata = array(
        'version'       => null,
        'description'   => null,
        'author'        => array(),
        'locale'        => null,
        'license'       => null,
        'url'           => null,
        'keyword'       => array(),
    );

    /// Instance of Erebot::Module::Wordlists this wordlist is associated with.
    protected $module;

    /// Name of this wordlist.
    protected $name;

    /// Path to the file where this wordlists is stored.
    protected $file;

    /// Database link as a PDO handle.
    protected $db;

    /// A collator compatible with this wordlist.
    protected $collator;

    /// An SQL query that returns the number of words in this wordlist.
    protected $countQuery;

    /// An SQL query to test whether some word is present in the list or not.
    protected $existsQuery;

    /// An SQL query to return the word at a given offset.
    protected $getQuery;


    /**
     * Constructs a new wordlist.
     *
     * \param Erebot::Module::Wordlists $module
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
        \Erebot\Module\Wordlists $module,
        $name,
        $file
    ) {
        $this->module   = $module;
        $this->name     = $name;
        $this->parseFile($file);
    }

    /**
     * Destroy the list, releasing any resource
     * it may have used.
     */
    public function __destruct()
    {
        // For PHARs, $this->file points to a temporary file
        // containing a copy of the original database.
        if (!strncasecmp(__FILE__, 'phar://', 7)) {
            unlink($this->file);
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
     * \throw Erebot::InvalidValueException
     *      The locale indicated in the file was invalid.
     */
    protected function parseFile($file)
    {
        $this->file    = $file;
        // When running as a PHAR, copy the SQLite database
        // to a temporary file. Required as the SQLite3 driver
        // does not support streams (and it can"t, see #55154).
        if (!strncasecmp(__FILE__, 'phar://', 7)) {
            $this->file = tempnam(
                sys_get_temp_dir(),
                'Wordlists'
            );
            copy($file, $this->file);
        }

        $this->db   = new \PDO('sqlite:' . $this->file);
        $metadata   = $this->db->query(
            'SELECT type, value '.
            'FROM metadata '.
            'ORDER BY type ASC, id ASC'
        );
        foreach ($metadata as $row) {
            if (!array_key_exists($row['type'], $this->metadata)) {
                continue;
            }
            if (is_array($this->metadata[$row['type']])) {
                $this->metadata[$row['type']][] = $row['value'];
            } else {
                $this->metadata[$row['type']] = $row['value'];
            }
        }

        // Try to create a collator for the given locale.
        $this->collator = new \Collator(
            str_replace('-', '_', $this->metadata['locale'])
        );
        // -127 = U_USING_DEFAULT_WARNING
        // -128 = U_USING_FALLBACK_WARNING.
        //    0 = U_ZERO_ERROR (no error).
        if (!in_array(intl_get_error_code(), array(-127, -128, 0))) {
            throw new \Erebot\InvalidValueException(
                "Invalid locale (".$this->metadata['locale']."): ".
                intl_get_error_message()
            );
        }
        // Ignore differences in case, accents and punctuation.
        $this->collator->setStrength(\Collator::PRIMARY);

        $this->countQuery = $this->db->prepare(
            'SELECT COUNT(1) '.
            'FROM words'
        );

        $this->getQuery = $this->db->prepare(
            'SELECT value '.
            'FROM words '.
            'ORDER BY sortkey ASC '.
            'LIMIT 1 OFFSET :offset'
        );

        $this->existsQuery = $this->db->prepare(
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
        $this->module->releaseList($this);
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
        return $this->collator;
    }

    /**
     * Returns the number of words in the list.
     *
     * \retval int
     *      Number of words in the list.
     */
    public function count()
    {
        $this->countQuery->execute();
        $res = $this->countQuery->fetchColumn();
        $this->countQuery->closeCursor();
        return (int) $res;
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
     *      Otherwise, \b null is returned.
     */
    public function findWord($word)
    {
        $key = $this->collator->getSortKey($word);

        /* In intl > 3.0.0a1, the key now ends with a trailing NUL byte.
         * We remove it for backward-compatibility with older releases. */
        if (substr($key, -1) === "\0") {
            $key = substr($key, 0, -1);
        }

        $this->existsQuery->execute(array(':key' => $key));
        $res = $this->existsQuery->fetchColumn();
        $this->existsQuery->closeCursor();
        if ($res === false || $res === null) {
            return null;
        }
        return $res;
    }

    /**
     * Tests whether the given word exists in the list.
     *
     * \param string $word
     *      Some word whose existence will be tested.
     *
     * \retval bool
     *      \b true if the given word is present in this list,
     *      \b false otherwise.
     *
     * \see
     *      Erebot::Module::Wordlists::Wordlist::findWord()
     */
    public function offsetExists($word)
    {
        $res = ($this->findWord($word) !== null);
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
     * \throw Erebot::InvalidValueException
     *      The given offset is not an integer.
     */
    public function offsetGet($offset)
    {
        if (!is_int($offset)) {
            throw new \Erebot\InvalidValueException('An integer was expected');
        }

        $this->getQuery->execute(array(':offset' => $offset));
        $res = $this->getQuery->fetchColumn();
        $this->getQuery->closeCursor();

        if ($res == '' || $res === null || $res === false) {
            return null;
        }
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
     *      that accept a single value (or \b null if the list does
     *      not provide any value) or an array for multi-valued
     *      types (an empty array may be returned in case the
     *      list does not provide any value).
     *
     * \throw Erebot::InvalidValueException
     *      The given metadata type is invalid.
     */
    public function getMetadata($type)
    {
        // Handle special cases here: name and path to file.
        if ($type == self::METADATA_NAME) {
            return $this->name;
        }
        if ($type == self::METADATA_FILE) {
            return $this->file;
        }

        if (!array_key_exists($type, $this->metadata)) {
            throw new \Erebot\InvalidValueException(
                'Invalid metadata type "' . $type . '"'
            );
        }
        return $this->metadata[$type];
    }
}
