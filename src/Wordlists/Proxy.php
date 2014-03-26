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
 *      Proxy class for a wordlist.
 *
 * This class provides the same set of methods as
 * Erebot_Module_Wordlists_Wordlist, but prevents
 * direct access to the wordlist itself.
 *
 * This was done so that a reference counting mechanism
 * could be hacked into the regular wordlists, which is
 * useful given the memory requirements of some wordlists.
 */
class Proxy implements \Countable, \ArrayAccess
{
    /// Internal list: an instance of Erebot_Module_Wordlists_Wordlist.
    private $list;

    /**
     * Constructs a new proxy for some wordlist.
     *
     * \param Erebot::Module::Wordlists::Wordlist $list
     *      The list this object is a proxy for.
     */
    final public function __construct(\Erebot\Module\Wordlists\Wordlist $list)
    {
        $this->list = $list;
    }

    /**
     * Destructs the proxy, releasing the internal
     * list in the process.
     */
    final public function __destruct()
    {
        $this->list->free();
    }

    /**
     * I'm not a big fan of cloning, sorry guys.
     */
    final public function __clone()
    {
        throw new \Erebot\Exception('Cloning this object is forbidden!');
    }

    /// \copydoc Erebot_Module_Wordlists_Wordlist::getCollator()
    final public function getCollator()
    {
        return $this->list->getCollator();
    }

    /// \copydoc Erebot_Module_Wordlists_Wordlist::count()
    final public function count()
    {
        return count($this->list);
    }

    /// \copydoc Erebot_Module_Wordlists_Wordlist::findWord()
    final public function findWord($word)
    {
        return $this->list->findWord($word);
    }

    /// \copydoc Erebot_Module_Wordlists_Wordlist::offsetGet()
    final public function offsetGet($offset)
    {
        return $this->list[$offset];
    }

    /// \copydoc Erebot_Module_Wordlists_Base::offsetSet()
    final public function offsetSet($offset, $value)
    {
        return $this->list[$offset] = $value;
    }

    /// \copydoc Erebot_Module_Wordlists_Wordlist::offsetExists()
    final public function offsetExists($word)
    {
        return isset($this->list[$word]);
    }

    /// \copydoc Erebot_Module_Wordlists_Base::offsetUnset()
    final public function offsetUnset($offset)
    {
        unset($this->list[$offset]);
    }

    /// \copydoc Erebot_Module_Wordlists_Wordlist::getMetadata()
    final public function getMetadata($type)
    {
        return $this->list->getMetadata($type);
    }
}
