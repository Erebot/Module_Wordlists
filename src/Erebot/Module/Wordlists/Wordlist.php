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
        $@Sux';

    /// Metadata associated with this list.
    protected $_metadata = array('locale' => '');

    /// Instance of Erebot_Module_Wordlists this wordlist is associated with.
    protected $_module;
    
    /// Name of this wordlist.
    protected $_name;
    
    /// An (alphabetically sorted) array of words contained in this wordlist.
    protected $_words;

    /// Path to the file where this wordlists is stored.
    protected $_file;

    /// A list of valid metadata types.
    static protected $_validMetadata = array('locale', 'encoding');

    static protected $_lower = NULL;
    static protected $_mbstring;
    static protected $_utf8_encode;
    static protected $_iconv;
    static protected $_recode;
    static protected $_html_decode;


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
        if (self::$_lower === NULL)
            self::_checkFeatures();

        $this->_module  = $module;
        $this->_name    = $name;
        $this->_words   = $this->_parseFile($file);
        $this->_file    = $file;
    }

    static protected function _checkFeatures()
    {
        self::$_mbstring = function_exists('mb_strtolower');
        self::$_utf8_encode = function_exists('utf8_encode');
        self::$_iconv = function_exists('iconv');
        self::$_recode = function_exists('recode');
        self::$_html_decode = function_exists('html_entity_decode');
        self::$_lower = (self::$_mbstring ? 'mb_strtolower' : 'strtolower');
    }

    /**
     * Parses the contents of a file, trying
     * to derive a wordlist from it.
     *
     * \param string $file
     *      Path to the file where the wordlist is stored.
     *
     * \note
     *      You may add comments (lines beginning with a #)
     *      in the file. Comments at the beginning of the file
     *      play a special role. They are used to give hints
     *      about the content of the file.
     *      These special comments are of the form "# type: hint"
     *      where "type" is currently one of "encoding" or "locale",
     *      and "hint" is the actual content for that hint.
     *      It does not matter in what order these special
     *      comments appear in the file, as long as they appear
     *      at the very beginning of the file (except that there
     *      MAY be a Byte Order Mark before them).
     *
     * \note
     *      This method automatically converts any word
     *      contained in the wordlist to UTF-8.
     *      The encoding for the input file may be specified
     *      using either a Byte Order Mark (BOM) or a comment
     *      at the beginning of the file, eg.:
     *          # encoding: ISO-8859-1
     *      By default, this method assumes the file is encoded
     *      in UTF-8.
     *
     * \note
     *      It is recommended that you add an indication on the locale
     *      the wordlist is intended for (eg. "en-US") in the file.
     *      You may do so by putting a comment at the beginning of the
     *      file, eg.:
     *          # locale: en-US
     *      This indication is used to sort words in the wordlist in
     *      alphabetical order, using the proper rules for that locale.
     *
     * \retval array
     *      A list with the words found in the given file,
     *      in alphabetical order. 
     */
    protected function _parseFile($file)
    {
        $content = file_get_contents($file);
        if ($content === FALSE)
            throw new Erebot_Module_Wordlists_UnreadableFileException($file);

        $encoding = self::_handleBOM($content);
        if ($encoding !== NULL)
            $content = self::_toUTF8($content, strtoupper($encoding));

        // Replaces a sequence of <whitespace><CR and/or LF><whitespace>
        // with a single linefeed (LF). This effectively removes empty lines
        // and lines containing only whitespaces.
        $content = preg_replace("/[ \\t\\f]*[\\r\\n]+\\s*/m", "\n", $content);
        $content = explode("\n", $content);

        while (count($content) && $content[0][0] == "#") {
            // Remove the line and strip the leading "#".
            $line   = (string) substr(array_shift($content), 1);
            $pos    = strpos($line, ':');

            if ($pos === FALSE)
                break;

            $key    = strtolower(trim((string) substr($line, 0, $pos)));
            $value  = ltrim((string) substr($line, $pos + 1));

            if (in_array($key, self::$_validMetadata))
                $this->_metadata[$key] = $value;
            else
                ; /// @TODO: log warning about unrecognized option.
        }

        if ($encoding !== NULL) {
            if (isset($this->_metadata['encoding']) &&
                strtoupper($this->_metadata['encoding']) != $encoding) {
                throw new Erebot_InvalidValueException(
                    "The encoding specified does not match the Byte Order Mark"
                );
            }
            $this->_metadata['encoding'] = strtoupper($encoding);
        }
        else if (!isset($this->_metadata['encoding']))
            $this->_metadata['encoding'] = 'UTF-8';

        // Normalize words (convert to UTF-8 and lowercase them).
        if (self::$_mbstring) {
            foreach ($content as $index => &$word) {
                $word = mb_strtolower(
                    self::_toUTF8($word, $this->_metadata['encoding']),
                    'UTF-8'
                );
            }
        }
        else {
            foreach ($content as $index => &$word) {
                $word = strtolower(
                    self::_toUTF8($word, $this->_metadata['encoding'])
                );
            }
        }

        // Try to create a collator for the given locale.
        $collator = new Collator(
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
        $collator->setStrength(Collator::PRIMARY);
        $this->_metadata['locale'] = $collator;
        // Sort the words (speeds up future lookups + assigns new keys).
        $collator->sort($content);
        return $content;
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

    /**
     * Returns the number of words in the list.
     *
     * \retval int
     *      Number of words in the list.
     */
    public function count()
    {
        return count($this->_words);
    }

    /**
     * Tests whether the given \b{word} exists in the list.
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
        // Dichotomic search using collation.
        $start = 0;
        $end = count($this->_words);
        if (!$end)
            return FALSE;

        while ($start < $end) {
            $middle = (int) $start + (($end - $start) / 2);
            $cmp = $this->_metadata['locale']->compare(
                $word,
                $this->_words[$middle]
            );

            if ($cmp === FALSE)
                throw new Exception('Internal error');

            if (!$cmp)
                return TRUE;
            else if ($cmp > 0)
                $start = $middle + 1;
            else
                $end = $middle;
        }
        return FALSE;
    }

    /**
     * Returns the word at the given offset.
     *
     * \param int $offset
     *      Offset of the word to retrieve.
     *
     * \retval string
     *      The word at the given $offset.
     */
    public function offsetGet($offset)
    {
        return $this->_words[$offset];
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
     *      Type of metadata to return. For the time being,
     *      the only valid types of metadata that this method
     *      supports are "locale" and "encoding".
     *
     * \retval mixed
     *      The requested metadata.
     *
     * \throw Erebot_NotFoundException
     *      No metadata could be found for the given type.
     */
    public function getMetadata($type)
    {
        if (!isset($this->_metadata[$type]))
            throw new Erebot_NotFoundException('No such metadata');
        return $this->_metadata[$type];
    }

    /**
     * Given some text, returns the name of its encoding,
     * if one could be deduced from a BOM (Byte Order Mark).
     *
     * \param string $text
     *      The text to analyze to try to deduce its encoding
     *      based on a Byte Order Mark.
     *
     * \retval mixed
     *      Either the name of an encoding (eg. "UTF-16BE")
     *      if one could be deduced from a Byte Order Mark
     *      present in the file; NULL otherwise.
     */
    static protected function _handleBOM(&$text)
    {
        if (substr($text, 0, 3) == "\xEF\xBB\xBF") {
            $text = (string) substr($text, 3);
            return 'UTF-8';
        }

        $four = substr($text, 0, 4);
        if ($four == "\x00\x00\xFF\xFE") {
            $text = (string) substr($text, 4);
            return "UTF-32BE";
        }

        if ($four == "\xFF\xFE\x00\x00") {
            $text = (string) substr($text, 4);
            return "UTF-32LE";
        }

        $two = substr($text, 0, 2);
        if ($two == "\xFE\xFF") {
            $text = (string) substr($text, 2);
            return "UTF-16BE";
        }

        if ($two == "\xFF\xFE") {
            $text = (string) substr($text, 2);
            return "UTF-16LE";
        }

        // This is some other 8-byte based character set.
        return NULL;
    }

    /**
     * Encodes some text in UTF-8.
     *
     * \param string $text
     *      Text to encode in UTF-8.
     *
     * \param string $from
     *      (optional) The text's current encoding.
     *
     * \retval string
     *      The text, encoded in UTF-8 or returned
     *      without any modification in case no
     *      mechanism could be found to change
     *      the text's encoding.
     *
     * \note
     *      This method has been duplicated from Erebot_Utils
     *      as we need a way to convert some random text to UTF-8
     *      without depending on Erebot's inner workings.
     *
     * \warning
     *      Contrary to Erebot's method, this method does not
     *      throw an exception when the given text could not
     *      be encoded in UTF-8 (because no mechanism could be
     *      found to do so). Instead, the text is returned
     *      unchanged.
     */
    static protected function _toUTF8($text, $from)
    {
        if ($from == 'UTF-8')
            return $text;

        if ($from == 'ISO-8859-1' && self::$_utf8_encode)
            return utf8_encode($text);

        if (self::$_iconv)
            return iconv($from, 'UTF-8//TRANSLIT', $text);

        if (self::$_recode)
            return recode($from.'..utf-8', $text);

        if (self::$_mbstring) {
            $subst  = mb_substitute_character('none');
            $text   = mb_convert_encoding($text, 'UTF-8', $from);
            mb_substitute_character($subst);
            return $text;
        }

        if (self::$_html_decode) {
            return html_entity_decode(
                htmlentities($text, ENT_QUOTES, $from),
                ENT_QUOTES,
                'UTF-8'
            );
        }

        throw new Erebot_NotImplementedException(
            "No way to do UTF-8 conversions"
        );
    }

    /**
     * Normalizes a word.
     *
     * \param string $word
     *      Word to normalize.
     *
     * \param mixed $key
     *      This parameter is ignored.
     *
     * \param string $encodings
     *      Encodings that may apply to the word.
     *
     * \return
     *      This method does not return anything.
     *
     * \warning
     *      $word is modified in place.
     *
     * \note
     *      This method's prototype is compatible
     *      with array_filter()'s expectations.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    static public function normalizeWord(&$word, $key, $encodings)
    {
        if ($encodings[1] === NULL)
            $word = self::_toUTF8($word, $encodings[0]);

        if (self::$_mbstring)
            $word = mb_strtolower($word, 'UTF-8');
        else
            $word = strtolower($word);
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
     *      separated using a single space (eg. "Fo'o. B4-r_").
     */
    static public function isWord($word)
    {
        if ($word === FALSE)
            return FALSE;
        return (bool) preg_match(self::WORD_FILTER, $word);
    }
}
