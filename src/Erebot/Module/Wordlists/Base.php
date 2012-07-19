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
 *      An abstract class meant to serve as a base
 *      to define a wrapper for a list of words.
 */
abstract class  Erebot_Module_Wordlists_Base
{
    /// Pattern used to recognize (composed) "words".
    const WORD_FILTER = '@^
        [\\p{N}\\p{L}\\-\\.\\(\\)_\']+          # A "word", ie. a sequence of
                                                # Unicode letters/numbers plus
                                                # some additional characters.
        (?:\\ [\\p{N}\\p{L}\\-\\.\\(\\)_\']+)?  # Another such word.
        $@Sux';

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
    final public function offsetSet($offset, $value)
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
    final public function offsetUnset($offset)
    {
        throw new Erebot_NotImplementedException();
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
