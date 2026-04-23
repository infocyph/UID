Sonyflake
=========

Class: ``Infocyph\\UID\\Sonyflake``

Bit Layout
----------

Sonyflake uses a 64-bit layout:

- 39 bits elapsed time in 10ms units from custom epoch
- 16 bits machine ID
- 8 bits sequence

Generation
----------

.. code-block:: php

   <?php

   use Infocyph\UID\Sonyflake;

   $id = Sonyflake::generate();
   $idFromMachine = Sonyflake::generate(machineId: 42);

Configuration Object
--------------------

Use ``Infocyph\\UID\\Configuration\\SonyflakeConfig`` for:

- fixed or callback-resolved machine ID
- custom epoch
- custom sequence provider
- clock-backward policy
- output type (string/int/binary)

.. code-block:: php

   <?php

   use Infocyph\UID\Configuration\SonyflakeConfig;
   use Infocyph\UID\Sonyflake;

   $config = new SonyflakeConfig(machineId: 42);
   $id = Sonyflake::generateWithConfig($config);

Validation and Parsing
----------------------

.. code-block:: php

   <?php

   use Infocyph\UID\Sonyflake;

   Sonyflake::isValid($id);
   $parsed = Sonyflake::parse($id);

``parse()`` output:

- ``time`` (DateTimeImmutable)
- ``sequence`` (int)
- ``machine_id`` (int)

Custom Epoch APIs
-----------------

- ``Sonyflake::setStartTimeStamp('2020-01-01 00:00:00')``
- ``Sonyflake::parseWithEpoch($id, $epochMs)``

Binary and Alternate Bases
--------------------------

- ``Sonyflake::toBytes($id)`` / ``Sonyflake::fromBytes($bytes)``
- ``Sonyflake::toBase($id, $base)`` / ``Sonyflake::fromBase($encoded, $base)``

Supported bases: ``16``, ``32``, ``36``, ``58``, ``62``.

Exception Types
---------------

- ``Infocyph\\UID\\Exceptions\\SonyflakeException``
- ``Infocyph\\UID\\Exceptions\\FileLockException``
