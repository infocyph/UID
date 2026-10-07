Sonyflake
=========

Class: ``Infocyph\\UID\\Sonyflake``

Bit Layout
----------

Sonyflake supports two explicit 64-bit formats. ``SonyflakeFormat::UID`` remains
the default for stored compatibility and uses 39-bit time / 16-bit machine /
8-bit sequence. ``SonyflakeFormat::UPSTREAM`` uses the upstream 39-bit time /
8-bit sequence / 16-bit machine layout. The two modes are not inferred from an
unlabelled integer.

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
- optional ``GenerationContext`` for clock/Runwire wait policy
- explicit ``SonyflakeFormat``

.. code-block:: php

   <?php

   use Infocyph\UID\Configuration\SonyflakeConfig;
   use Infocyph\UID\Sonyflake;

   $config = new SonyflakeConfig(machineId: 42);
   $id = Sonyflake::generateWithConfig($config);

   $upstream = Sonyflake::generateWithConfig(
       new SonyflakeConfig(
           machineId: 42,
           format: \Infocyph\UID\Enums\SonyflakeFormat::UPSTREAM,
       ),
   );

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

- ``Sonyflake::parse($id, $format)``
- ``Sonyflake::parseWithEpoch($id, $epochMs, $format)``

The epoch is immutable domain configuration: supply it through a config and
retain both epoch and format metadata when parsing. The upstream mode uses its
upstream default epoch unless a custom epoch is explicitly configured. Changing
format or epoch creates a different ID domain.

Binary and Alternate Bases
--------------------------

- ``Sonyflake::toBytes($id)`` / ``Sonyflake::fromBytes($bytes)``
- ``Sonyflake::toBase($id, $base)`` / ``Sonyflake::fromBase($encoded, $base)``

Supported bases: ``10``, ``16``, ``32``, ``36``, ``58``, ``62``.

Exception Types
---------------

- ``Infocyph\\UID\\Exceptions\\SonyflakeException``
- ``Infocyph\\UID\\Exceptions\\FileLockException``
