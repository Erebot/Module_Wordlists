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

    protected $_metadata = array('locale' => '');
    protected $_module;
    protected $_name;
    protected $_words;
    protected $_file;

    static protected $_validMetadata = array('locale', 'encoding');

    public function __construct(
        Erebot_Module_Wordlists $module,
                                $name,
                                $file
    )
    {
        $this->_module  = $module;
        $this->_name    = $name;
        $this->_words   = $this->_parseFile($file);
        $this->_file    = $file;
    }

    protected function _parseFile($file)
    {
        $content = file_get_contents($file);
        if ($content === FALSE)
            throw new Erebot_Module_Wordlists_UnreadableFileException($file);

        $encoding = self::_handleBOM($content);
        if ($encoding !== NULL)
            $content = self::_toUTF8($content, $encoding);

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
            $this->_metadata['encoding'] = $encoding;
        }
        else if (!isset($this->_metadata['encoding']))
            $this->_metadata['encoding'] = 'UTF-8';
        $this->_metadata['encoding'] = strtoupper($this->_metadata['encoding']);

        // Normalize words (convert to UTF-8 and lowercase them).
        // Things that don't look like words are replaced with FALSE.
        $ok = array_walk(
            $content,
            array($this, 'normalizeWord'),
            array($this->_metadata['encoding'], $encoding)
        );

        if (!$ok) {
            throw new Erebot_Module_Wordlists_UnreadableFileException(
                $file
            );
        }

        // Remove invalid words.
        $content = array_filter($content, array('self', 'isWord'));

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
        // Sort the words (speeds up future lookups).
        $collator->sort($content);
        return $content;
    }

    public function free()
    {
        $this->_module->releaseList($this);
    }

    public function getName()
    {
        return $this->_name;
    }

    public function getFile()
    {
        return $this->_file;
    }

    public function count()
    {
        return count($this->_words);
    }

    public function offsetExists($offset)
    {
        // Dichotomic search using collation.
        $start = 0;
        $end = count($this->_words);
        if (!$end)
            return FALSE;

        while ($start < $end) {
            $middle = (int) $start + (($end - $start) / 2);
            $cmp = $this->_metadata['locale']->compare(
                $offset,
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

    public function offsetGet($offset)
    {
        return $this->_words[$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new Erebot_NotImplementedException();
    }

    public function offsetUnset($offset)
    {
        throw new Erebot_NotImplementedException();
    }

    public function getMetadata($data)
    {
        if (!isset($this->_metadata[$data]))
            throw new Erebot_NotFoundException('No such metadata');
        return $this->_metadata[$data];
    }

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
        if (!strcasecmp($from, 'utf-8'))
            return $text;

        if (!strcasecmp($from, 'iso-8859-1') &&
            function_exists('utf8_encode'))
            return utf8_encode($text);

        if (function_exists('iconv'))
            return iconv($from, 'UTF-8//TRANSLIT', $text);

        if (function_exists('recode'))
            return recode($from.'..utf-8', $text);

        if (function_exists('mb_convert_encoding')) {
            $subst  = mb_substitute_character('none');
            $text   = mb_convert_encoding($text, 'UTF-8', $from);
            mb_substitute_character($subst);
            return $text;
        }

        if (function_exists('html_entity_decode')) {
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
     * \param string $encoding
     *      Encoding of the word.
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

        if (function_exists('mb_strtolower'))
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
