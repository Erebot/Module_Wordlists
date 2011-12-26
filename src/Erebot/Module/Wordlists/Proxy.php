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

class       Erebot_Module_Wordlists_Proxy
implements  Countable,
            ArrayAccess
{
    protected $_list;

    final public function __construct(Erebot_Module_Wordlists_Wordlist $list)
    {
        $this->_list = $list;
    }

    final public function __destruct()
    {
        $this->_list->free();
    }

    public function getName()
    {
        return $this->_list->getName();
    }

    public function getFile()
    {
        return $this->_list->getFile();
    }

    public function count()
    {
        return count($this->_list);
    }

    public function offsetGet($offset)
    {
        return $this->_list[$offset];
    }

    public function offsetSet($offset, $value)
    {
        return $this->_list[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->_list[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->_list[$offset]);
    }

    public function getMetadata($data)
    {
        return $this->_list->getMetadata($data);
    }
}
