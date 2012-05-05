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

class   PolicyHelper
extends Erebot_Module_Wordlists
{
    static public function compilePolicy($simplePatterns)
    {
        return self::_compilePolicy($simplePatterns);
    }

    static public function filterLists($lists, $patterns)
    {
        return self::_filterLists($lists, $patterns);
    }
}

class   PolicyTest
extends Erebot_Testenv_Module_TestCase
{
    public function setUp()
    {
        $this->_module = new PolicyHelper(NULL);
        parent::setUp();
    }

    public function testPrepareEmptyFilters()
    {
        $input      = array();
        // The default policy should accept anything.
        $expected   = '/^(?:.*)$/Si';

        $output     = $this->_module->compilePolicy($input);
        $this->assertEquals($expected, $output);
    }

    public function testPrepareVariousFilters()
    {
        $input      = array('a', '!b', 'c*', '!d*', 'e?', '!f?');
        $expected   = '/^(?:a|(?!b)(?:c.*|(?!d.*)(?:e.|(?!f.)(?:.*))))$/Si';
        $output     = $this->_module->compilePolicy($input);
        $this->assertEquals($expected, $output);
    }

    public function testFilterUsingDefaultPolicy()
    {
        $input      = array('foo' => NULL, 'bar' => NULL, 'baz' => NULL);
        // Default policy.
        $policy     = $this->_module->compilePolicy(array());
        // The default policy is to allow everything.
        // So we expected the output to be the same as the input.
        $expected   = $input;

        $output     = $this->_module->filterLists($input, $policy);
        $this->assertEquals($expected, $output, $policy);
    }

    public function testFilterFallbackToDefaultPolicy()
    {
        $input      = array('foo' => NULL, 'bar' => NULL, 'baz' => NULL);
        // This policy rejects "qux" and allows everything else.
        $policy     = $this->_module->compilePolicy(array('!qux'));
        // We expected the output to be the same as the input.
        $expected   = $input;

        $output     = $this->_module->filterLists($input, $policy);
        $this->assertEquals($expected, $output, $policy);
    }

    public function testExplicitelyAcceptAnything()
    {
        $input      = array('foo' => NULL, 'bar' => NULL, 'baz' => NULL);
        // This policy accepts everything (acts like the default one).
        $policy     = $this->_module->compilePolicy(array('*'));
        // We expected the output to be the same as the input.
        $expected   = $input;

        $output     = $this->_module->filterLists($input, $policy);
        $this->assertEquals($expected, $output, $policy);
    }

    public function testRejectByDefault()
    {
        $input      = array('foo' => NULL, 'bar' => NULL, 'baz' => NULL);
        // This policy accepts "bar" but rejects everything else.
        $policy     = $this->_module->compilePolicy(array('bar', '!*'));
        $expected   = array('bar' => NULL);

        $output     = $this->_module->filterLists($input, $policy);
        $this->assertEquals($expected, $output, $policy);
    }

    public function testPartialBlacklist()
    {
        $input      = array('foo' => NULL, 'bar' => NULL, 'baz' => NULL);
        // This policy accepts "bar" but rejects everything else.
        $policy     = $this->_module->compilePolicy(array('!ba?'));
        $expected   = array('foo' => NULL);

        $output     = $this->_module->filterLists($input, $policy);
        $this->assertEquals($expected, $output, $policy);
    }

    public function testBlacklist()
    {
        $input      = array('foo' => NULL, 'bar' => NULL, 'baz' => NULL);
        // This policy accepts "bar" but rejects everything else.
        $policy     = $this->_module->compilePolicy(array('!*', 'foo'));
        $expected   = array();

        $output     = $this->_module->filterLists($input, $policy);
        $this->assertEquals($expected, $output, $policy);
    }
}

