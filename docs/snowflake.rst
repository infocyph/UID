Snowflake
=========

Class: ``Infocyph\\UID\\Snowflake``

Bit Layout
----------

Snowflake follows a 64-bit layout:

- 41 bits timestamp (ms from custom epoch)
- 5 bits datacenter
- 5 bits worker
- 12 bits sequence

Generation
----------

.. code-block:: php

   <?php

   use Infocyph\UID\Snowflake;

   $id = Snowflake::generate();
   $idFromNode = Snowflake::generate(datacenter: 1, workerId: 7);

Configuration Object
--------------------

Use ``Infocyph\\UID\\Configuration\\SnowflakeConfig`` for advanced control:

- fixed ``datacenterId`` and ``workerId``
- ``nodeResolver`` callback
- ``customEpoch`` (``DateTimeInterface`` or integer milliseconds)
- custom ``sequenceProvider``
- ``ClockBackwardPolicy`` (``WAIT`` or ``THROW``)

.. code-block:: php

   <?php

   use Infocyph\UID\Configuration\SnowflakeConfig;
   use Infocyph\UID\Enums\ClockBackwardPolicy;
   use Infocyph\UID\Snowflake;

   $config = new SnowflakeConfig(
       datacenterId: 2,
       workerId: 3,
       customEpoch: 1_577_836_800_000,
       clockBackwardPolicy: ClockBackwardPolicy::WAIT,
   );

   $id = Snowflake::generateWithConfig($config);

Validation and Parsing
----------------------

.. code-block:: php

   <?php

   use Infocyph\UID\Snowflake;

   Snowflake::isValid($id);
   $parsed = Snowflake::parse($id);

``parse()`` output:

- ``time`` (DateTimeImmutable)
- ``sequence`` (int)
- ``worker_id`` (int)
- ``datacenter_id`` (int)

Custom Epoch APIs
-----------------

- ``Snowflake::parseWithEpoch($id, $epochMs)``

The epoch is immutable global-domain configuration: supply it through a config
and retain it when parsing. Changing an epoch creates a different ID domain.

Binary and Alternate Bases
--------------------------

- ``Snowflake::toBytes($id)`` / ``Snowflake::fromBytes($bytes)``
- ``Snowflake::toBase($id, $base)`` / ``Snowflake::fromBase($encoded, $base)``

Supported bases: ``16``, ``32``, ``36``, ``58``, ``62``.

Exception Types
---------------

- ``Infocyph\\UID\\Exceptions\\SnowflakeException``
- ``Infocyph\\UID\\Exceptions\\FileLockException``
