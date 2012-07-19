Prerequisites
=============

The following dependencies must be installed in order for this module
to work properly:

*   PHP >= 5.2.2
*   Erebot (some specific version may be required depending
    on this module's version)
*   Erebot_API (some specific version may be required depending
    on this module's version)
*   The :pecl:`intl` extension >= 1.0.3 (bundled with PHP since 5.3.0).

    ..  warning::
        Due to a bug (https://bugs.php.net/bug.php?id=62070) in all versions
        up to 3.0.0a1 (included) and the way internals worked in older releases
        of PHP, users of PHP 5.2.x **MUST** install a :pecl:`intl` > 3.0.0a1
        **or PHP will crash** when using this module. Newer PHP releases are
        unaffected as a workaround is available for those versions.

*   The :pecl:`PDO` extension (bundled with PHP since 5.1.0).
*   The :pecl:`PDO_SQLITE` extension (bundled with PHP since 5.1.0).

Erebot (core) adds its own set of `prerequisites`_ which usually superseed
those.

..  _`Erebot_Module_Wordlists`:
    http://erebot.github.com/Erebot_Module_Wordlists/
..  _`prerequisites`:
    http://erebot.github.com/Erebot/Prerequisites.html

.. vim: ts=4 et

