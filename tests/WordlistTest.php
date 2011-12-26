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

class   WordlistHelper
extends Erebot_Module_Wordlists_Wordlist
{
    public function __construct($file)
    {
        $this->_file    = $file;
        $this->_name    =
        $this->_module  = NULL;
        $this->_words   = $this->_parseFile($file);
    }
}

class   WordlistTest
extends PHPUnit_Framework_TestCase
{
    const U_LATIN_SMALL_LETTER_A_WITH_GRAVE         = "\xC3\xA0";   // U+00E0
    const U_LATIN_SMALL_LETTER_C_WITH_CEDILLA       = "\xC3\xA7";   // U+00E7
    const U_LATIN_SMALL_LETTER_E_WITH_GRAVE         = "\xC3\xA8";   // U+00E8
    const U_LATIN_SMALL_LETTER_E_WITH_ACUTE         = "\xC3\xA9";   // U+00E9
    const U_LATIN_SMALL_LETTER_I_WITH_DIAERESIS     = "\xC3\xAF";   // U+00EF
    const U_LATIN_SMALL_LETTER_U_WITH_GRAVE         = "\xC3\xB9";   // U+00F9
    const U_LATIN_SMALL_LETTER_U_WITH_CIRCUMFLEX    = "\xC3\xBB";   // U+00FB
    const U_LATIN_SMALL_LETTER_U_WITH_DIARESIS      = "\xC3\xBC";   // U+00FC
    const U_LATIN_SMALL_LIGATURE_OE                 = "\xC5\x93";   // U+0153

    public function setUp()
    {
        parent::setUp();
        $this->_base =  dirname(__FILE__) .
                        DIRECTORY_SEPARATOR . 'wordlists' .
                        DIRECTORY_SEPARATOR;
    }

    public function test_ISO_8859_1()
    {
        $list   = new WordlistHelper($this->_base . 'iso-8859-1.txt');
        $total  = count($list);
        $this->assertEquals(3, $total);
        // The encoding must have been picked up by the class.
        $this->assertEquals('ISO-8859-1', $list->getMetadata('encoding'));

        // Expected words, encoded using UTF-8.
        $expected = array(
            // ça
            self::U_LATIN_SMALL_LETTER_C_WITH_CEDILLA . "a",
            // ouïe
            "ou" . self::U_LATIN_SMALL_LETTER_I_WITH_DIAERESIS . "e",
            // père
            "p" . self::U_LATIN_SMALL_LETTER_E_WITH_GRAVE . "re",
        );
        for ($i = 0; $i < $total; $i++)
            $this->assertEquals($expected[$i], $list[$i]);
    }

    public function test_ISO_8859_15()
    {
        $list   = new WordlistHelper($this->_base . 'iso-8859-15.txt');
        $total  = count($list);
        $this->assertEquals(3, $total);
        // The encoding must have been picked up by the class.
        $this->assertEquals('ISO-8859-15', $list->getMetadata('encoding'));

        // Expected words, encoded using UTF-8.
        $expected = array(
            // été
            self::U_LATIN_SMALL_LETTER_E_WITH_ACUTE . "t" .
            self::U_LATIN_SMALL_LETTER_E_WITH_ACUTE,
            // où
            "o" . self::U_LATIN_SMALL_LETTER_U_WITH_GRAVE,
            // sœur
            "s" . self::U_LATIN_SMALL_LIGATURE_OE . "ur",
        );
        for ($i = 0; $i < $total; $i++)
            $this->assertEquals($expected[$i], $list[$i]);
    }

    public function test_UTF_8()
    {
        $list   = new WordlistHelper($this->_base . 'utf-8.txt');
        $total  = count($list);
        $this->assertEquals(3, $total);
        // This is the default encoding.
        $this->assertEquals('UTF-8', $list->getMetadata('encoding'));

        // Expected words, encoded using UTF-8.
        $expected = array(
            // épithète
            self::U_LATIN_SMALL_LETTER_E_WITH_ACUTE . "pith" .
            self::U_LATIN_SMALL_LETTER_E_WITH_GRAVE . "te",
            // là
            "l" . self::U_LATIN_SMALL_LETTER_A_WITH_GRAVE,
            // saoûl
            "sao" . self::U_LATIN_SMALL_LETTER_U_WITH_CIRCUMFLEX . "l",
        );
        for ($i = 0; $i < $total; $i++)
            $this->assertEquals($expected[$i], $list[$i]);
    }

    public function test_UTF_16_BE()
    {
        $list   = new WordlistHelper($this->_base . 'utf-16be.txt');
        $total  = count($list);
        $this->assertEquals(3, $total);
        // Check automatically detected encoding (due to the BOM).
        $this->assertEquals('UTF-16BE', $list->getMetadata('encoding'));

        // Expected words, encoded using UTF-8.
        $expected = array(
            // balançoire
            "balan" . self::U_LATIN_SMALL_LETTER_C_WITH_CEDILLA . "oire",
            // dégénère
            "d" . self::U_LATIN_SMALL_LETTER_E_WITH_ACUTE .
            "g" . self::U_LATIN_SMALL_LETTER_E_WITH_ACUTE .
            "n" . self::U_LATIN_SMALL_LETTER_E_WITH_GRAVE . "re",
            // mère
            "m" . self::U_LATIN_SMALL_LETTER_E_WITH_GRAVE . "re",
        );
        for ($i = 0; $i < $total; $i++)
            $this->assertEquals($expected[$i], $list[$i]);
    }

    public function test_UTF_16_LE()
    {
        $list   = new WordlistHelper($this->_base . 'utf-16le.txt');
        $total  = count($list);
        $this->assertEquals(3, $total);
        // Check automatically detected encoding (due to the BOM).
        $this->assertEquals('UTF-16LE', $list->getMetadata('encoding'));

        // Expected words, encoded using UTF-8.
        $expected = array(
            // ambigü
            "ambig" . self::U_LATIN_SMALL_LETTER_U_WITH_DIARESIS,
            // dû
            "d" . self::U_LATIN_SMALL_LETTER_U_WITH_CIRCUMFLEX,
            // phénomène
            "ph" . self::U_LATIN_SMALL_LETTER_E_WITH_ACUTE .
            "nom" . self::U_LATIN_SMALL_LETTER_E_WITH_GRAVE . "ne",
        );
        for ($i = 0; $i < $total; $i++)
            $this->assertEquals($expected[$i], $list[$i]);
    }

    /**
     * @expectedException Erebot_InvalidValueException
     */
    public function testBadBOM()
    {
        // Try to load a file whose BOM marks it as being
        // encoded in UTF-8 while its contents says its ISO-8859-1.
        $list = new WordlistHelper($this->_base . 'bad-BOM.txt');
    }

    public function testAccents()
    {
        $list = new WordlistHelper($this->_base . 'utf-8.txt');
        // First, an exact match.
        $word = "l" . self::U_LATIN_SMALL_LETTER_A_WITH_GRAVE;
        $this->assertTrue(isset($list[$word]), "'là' not found");
        // Then, make sure accents are ignored during the comparison.
        $this->assertTrue(isset($list['la']), "Accents are not ignored");
        // Last, make sure case is also ignored for accentuated words (LÀ).
        $this->assertTrue(isset($list["L\xC3\x80"]), "Case is not ignored");
    }
}


