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

class   Erebot_Module_Wordlists
extends Erebot_Module_Base
{
    /// List of registered paths where wordlists are kept.
    static protected $_paths = array();
    static protected $_cache = NULL;
    static protected $_refs  = array();

    /**
     * This method is called whenever the module is (re)loaded.
     *
     * \param int $flags
     *      A bitwise OR of the Erebot_Module_Base::RELOAD_*
     *      constants. Your method should take proper actions
     *      depending on the value of those flags.
     *
     * \note
     *      See the documentation on individual RELOAD_*
     *      constants for a list of possible values.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function _reload($flags)
    {
        if (!count(self::$_paths)) {
            $base = '@data_dir@';
            // Running from the repository.
            if ($base == '@'.'data_dir'.'@') {
                $parts = array(
                    dirname(dirname(dirname(dirname(__FILE__)))),
                    'data',
                    'lists',
                );
            }
            else {
                $parts = array(
                    dirname($base . DIRECTORY_SEPARATOR),
                    'Erebot_Module_Wordlists',
                    'lists',
                );
            }
            self::$_paths = array(implode(DIRECTORY_SEPARATOR, $parts));
            self::$_cache = NULL;
        }
    }

    /// \copydoc Erebot_Module_Base::_unload()
    protected function _unload()
    {
    }

    /**
     * Returns the names of available lists.
     *
     * \retval list
     *      The names of all available lists.
     */
    static public function getAvailableLists()
    {
        if (self::$_cache)
            return array_keys(self::$_cache);

        $lists = array();
        foreach (self::$_paths as $path) {
            $files = scandir($path);
            foreach ($files as $file) {
                if (substr($file, -4) == '.txt') {
                    $name = strtolower(substr($file, 0, -4));
                    if (isset($lists[$name]))
                        ; /// @TODO: log a warning
                    else
                        $lists[$name] = $path . DIRECTORY_SEPARATOR . $file;
                }
            }
        }
        self::$_cache = $lists;
        return array_keys($lists);
    }

    static public function registerPath($path)
    {
        $path = realpath($path);
        if (!in_array($path, self::$_paths)) {
            self::$_paths[] = $path;
            self::$_cache   = NULL;
        }
    }

    static public function unregisterPath($path)
    {
        $path   = realpath($path);
        $key    = array_search(self::$_paths, $path);
        if ($key === FALSE)
            throw new Erebot_InvalidValueException('No such path registered');
        unset(self::$_paths[$key]);
        // Reindex entries.
        self::$_paths = array_values(self::$_paths);
        self::$_cache = NULL;
    }

    /**
     * Returns a list of words.
     *
     * \param string $list
     *      Name of the list to return.
     *
     * \throw Erebot_Module_Wordlists_BadListNameException
     *      The given $list is not a valid list name.
     */
    public function getList($list)
    {
        if (isset(self::$_refs[$list])) {
            self::$_refs[$list]['counter']++;
            return Erebot_Module_Wordlists_Proxy(
                self::$_refs[$list]['instance']
            );
        }

        $lists = self::getAvailableLists();
        if (!in_array($list, $lists))
            throw new Erebot_Module_Wordlists_BadListNameException($list);

        $listObject = new Erebot_Module_Wordlists_Wordlist(
            $this,
            $list,
            self::$_cache[$list]
        );

        self::$_refs[$list] = array(
            'counter'   => 1,
            'instance'  => $listObject,
        );
        return new Erebot_Module_Wordlists_Proxy($listObject);
    }

    public function releaseList(Erebot_Module_Wordlists_Wordlist $list)
    {
        $name = $list->getName();
        if (!isset(self::$_refs[$name]))
            throw new Erebot_NotFoundException('No such list');

        if (--self::$_refs[$name]['counter'] <= 0)
            unset(self::$_refs[$name]);
    }
}

