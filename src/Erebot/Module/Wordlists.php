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
 *      A module that provides lists of words for other modules
 *      to use.
 *
 * This is mainly useful for games and quizzes.
 */
class   Erebot_Module_Wordlists
extends Erebot_Module_Base
{
    /// List of registered paths where wordlists are kept.
    static protected $_paths = array();

    /// A cache with available wordlists, based on currently registered paths.
    static protected $_cache = NULL;

    /// Ref-counted cache of wordlists.
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

        $cls = $this->getFactory('!Callable');
        $this->registerHelpMethod(new $cls(array($this, 'getHelp')));
    }

    /**
     * Provides help about this module.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Some help request.
     *
     * \param Erebot_Interface_TextWrapper $words
     *      Parameters passed with the request. This is the same
     *      as this module's name when help is requested on the
     *      module itself (in opposition with help on a specific
     *      command provided by the module).
     */
    public function getHelp(
        Erebot_Interface_Event_Base_TextMessage $event,
        Erebot_Interface_TextWrapper            $words
    )
    {
        if ($event instanceof Erebot_Interface_Event_Base_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

        $fmt        = $this->getFormatter($chan);
        $moduleName = strtolower(get_class());
        $nbArgs     = count($words);

        if ($nbArgs == 1 && $words[0] == $moduleName) {
            $msg = $fmt->_(
                "This module does not provide any command, but ".
                "provides lists of words for other modules to use."
            );
            $this->sendMessage($target, $msg);
            return TRUE;
        }
    }

    /**
     * Returns the names of available lists.
     *
     * \retval list
     *      The names of all available lists.
     */
    public function getAvailableLists()
    {
        if (self::$_cache !== NULL)
            return array_keys(self::$_cache);

        $lists = array();
        foreach (self::$_paths as $path) {
            $files = scandir($path);
            foreach ($files as $file) {
                if (substr($file, -7) == '.sqlite') {
                    $name = strtolower(substr($file, 0, -7));
                    if (isset($lists[$name]))
                        ; /// @TODO: log a warning
                    else
                        $lists[$name] = $path . DIRECTORY_SEPARATOR . $file;
                }
            }
        }

        // Create a regexp pattern for the policy
        // and filter the lists according to that policy.
        $policy = self::_compilePolicy(
            explode(' ', $this->parseString('policy', ''))
        );
        $lists = self::_filterLists($lists, $policy);

        self::$_cache = $lists;
        return array_keys($lists);
    }

    /**
     * Given a list of simple patterns containing only
     * the '?' and '*' characters as wildcards, compiles
     * a regexp pattern matching the policy expressed by
     * those patterns.
     *
     * \param array $simplePatterns
     *      A list of simple patterns. The characters '?' and '*'
     *      may be used as wildcards to match exactly one,
     *      or one or more characters, respectively.
     *      In additional, if the given pattern starts with a '!',
     *      occurrences matching that pattern will be rejected.
     *      Hence, array('foo', '!*'); accepts 'foo' but rejects
     *      everything else.
     *
     * \retval string
     *      A regexp pattern matching the policy.
     *
     * \note
     *      The patterns are run in the same order as they
     *      appear in the given array. Therefore, if given
     *      something like array('!*', 'foo');, the resulting
     *      policy will reject any string (even 'foo') because
     *      the rejection rule matches first.
     *
     * \note
     *      The default policy is to accept all entries
     *      that did not match. If you want to reject these
     *      instead, you must add '!*' manually as the last
     *      pattern in the given array.
     */
    static protected function _compilePolicy($simplePatterns)
    {
        $policy         = '(?:.*)'; // Default policy = accept everything.
        $simplePatterns = array_reverse($simplePatterns);
        foreach ($simplePatterns as $simplePattern) {
            $simplePattern = trim($simplePattern);
            if ($simplePattern == "" || $simplePattern == "!")
                continue;

            $negate     = FALSE;
            if ($simplePattern[0] == "!") {
                $simplePattern  = substr($simplePattern, 1);
                $negate         = TRUE;
            }

            // Parse this simple pattern and create an equivalent
            // regular expression.
            $pattern    = "";
            for ($i = 0, $len = strlen($simplePattern); $i < $len; $i++) {
                $suffix = '*';
                switch ($simplePattern[$i]) {
                    case '?':
                        $suffix = '';
                        // Don't break.
                    case '*':
                        $pattern .= '.' . $suffix;
                        break;
                    default:
                        $pattern .= preg_quote($simplePattern[$i], '/');
                }
            }

            if ($negate)
                $policy = '(?!'.$pattern.')'.$policy;
            else
                $policy = '(?:'.$pattern.'|'.$policy.')';
        }

        $policy = '/^'.$policy.'$/Si';
        return $policy;
    }

    /**
     * Given a mapping of wordlists' names with their path
     * and a policy, returns the same mapping with only
     * those entries whose name matches the policy.
     *
     * \param array $lists
     *      A mapping of wordlists (indexed by their names)
     *      to their path.
     *
     * \param string $policy
     *      A policy (as a regular expression pattern).
     *
     * \retval array
     *      A mapping of wordlists (indexed by their names)
     *      to their path, contaning only those wordlists
     *      that were allowed by the given policy.
     */
    static protected function _filterLists($lists, $policy)
    {
        $res        = array();
        foreach ($lists as $name => $path) {
            if ((bool) preg_match($policy, $name))
                $res[$name] = $path;
        }
        return $res;
    }

    /**
     * Registers a new path containing wordlists.
     *
     * \param string $path
     *      New path to register.
     *
     * \return
     *      This method does not return anything.
     */
    static public function registerPath($path)
    {
        $path = realpath($path);
        if (!in_array($path, self::$_paths)) {
            self::$_paths[] = $path;
            self::$_cache   = NULL;
        }
    }

    /**
     * Unregisters a path that was previously registered
     * using Erebot_Module_Wordlists::registerPath().
     *
     * \param string $path
     *      Path to unregister.
     *
     * \return
     *      This method does not return anything.
     */
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

        $lists = $this->getAvailableLists();
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

    /**
     * Releases a wordlist.
     *
     * This decreases the reference counter associated
     * with the list and removes it from the cache
     * entirely when the counter reaches zero.
     *
     * \param Erebot_Module_Wordlists_Wordlist $list
     *      Wordlist to release.
     */
    public function releaseList(Erebot_Module_Wordlists_Wordlist $list)
    {
        $nameType = Erebot_Module_Wordlists_Wordlist::METADATA_NAME;
        $name = $list->getMetadata($nameType);
        if (!isset(self::$_refs[$name]))
            throw new Erebot_NotFoundException('No such list');

        if (--self::$_refs[$name]['counter'] <= 0)
            unset(self::$_refs[$name]);
    }
}

