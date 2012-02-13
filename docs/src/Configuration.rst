Configuration
=============

.. _`configuration options`:

Options
-------

This module does not provide any configuration option.

Example
-------

The recommended way to use this module is to have it loaded at the general
configuration level and to disable it only for specific networks.

..  parsed-code:: xml

    <?xml version="1.0" ?>
    <configuration
      xmlns="http://localhost/Erebot/"
      version="..."
      language="fr-FR"
      timezone="Europe/Paris"
      commands-prefix="!">

      <modules>
        <!-- Other modules ignored for clarity. -->

        <!-- We just make sure that this module is loaded. -->
        <module name="|project|"/>
      </modules>
    </configuration>

.. vim: ts=4 et

