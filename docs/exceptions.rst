Exceptions
==========

Hierarchy
---------

- ``Infocyph\\UID\\Exceptions\\UIDException``
- ``Infocyph\\UID\\Exceptions\\UUIDException``
- ``Infocyph\\UID\\Exceptions\\ULIDException``
- ``Infocyph\\UID\\Exceptions\\SnowflakeException``
- ``Infocyph\\UID\\Exceptions\\SonyflakeException``
- ``Infocyph\\UID\\Exceptions\\FileLockException``

Usage Pattern
-------------

Catch specific exceptions when you need algorithm-level handling,
or catch ``UIDException`` for a package-wide fallback.

.. code-block:: php

   <?php

   use Infocyph\UID\Exceptions\UIDException;
   use Infocyph\UID\UUID;

   try {
       $uuid = UUID::normalize('{INVALID}');
   } catch (UIDException $e) {
       // Handle UID package errors.
   }
