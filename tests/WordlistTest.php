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
extends \Erebot\Module\Wordlists\Wordlist
{
    public function __construct($file)
    {
        $this->file     = $file;
        $this->name     = basename($file, '.sqlite');
        $this->module   = NULL;
        $this->parseFile($file);
    }
}

class   WordlistTest
extends \PHPUnit\Framework\TestCase
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

    public function testWordlist()
    {
        $list   = new WordlistHelper($this->_base . 'count.sqlite');

        // Check words count.
        $total  = count($list);
        $this->assertEquals(4, $total);

        // Check wordlist metadata.
        $metadata = array(
            WordlistHelper::METADATA_NAME           => 'count',
            WordlistHelper::METADATA_VERSION        => '1.0',
            WordlistHelper::METADATA_DESCRIPTION    => 'Test data.',
            WordlistHelper::METADATA_AUTHORS        => array('Clicky', 'test'),
            WordlistHelper::METADATA_LOCALE         => 'fr-FR',
            WordlistHelper::METADATA_LICENSE        => 'GPLv3',
            WordlistHelper::METADATA_URL            => 'https://erebot.net/',
            WordlistHelper::METADATA_KEYWORDS       => array('test', 'data'),
        );
        foreach ($metadata as $type => $value)
            $this->assertEquals($value, $list->getMetadata($type));

        // Check the actual words (and their order).
        $expected = array(
            // cœur
            "c" . self::U_LATIN_SMALL_LIGATURE_OE . 'ur',
            // épithète
            self::U_LATIN_SMALL_LETTER_E_WITH_ACUTE . 'pith' .
            self::U_LATIN_SMALL_LETTER_E_WITH_GRAVE . 'te',
            // Là
            'l' . self::U_LATIN_SMALL_LETTER_A_WITH_GRAVE,
            // saoûl
            'sao' . self::U_LATIN_SMALL_LETTER_U_WITH_CIRCUMFLEX . 'l',
        );
        for ($i = 0; $i < $total; $i++)
            $this->assertEquals($expected[$i], $list[$i]);
    }

    public function existenceProvider()
    {
        $words  = array('la', 'sAoUl', 'coeur', 'ÉPITHÈTE');
        $args = array();
        foreach ($words as $word)
            $args[] = array($word);
        return $args;
    }

    /**
     * @dataProvider existenceProvider
     */
    public function testExistence($word)
    {
        $list = new WordlistHelper($this->_base . 'count.sqlite');
        $this->assertTrue(isset($list[$word]));
    }
}
