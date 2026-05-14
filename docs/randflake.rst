Randflake
=========

Class: ``Infocyph\\UID\\Randflake``

Overview
--------

Randflake is a lease-bound 64-bit ID family with encrypted payload fields.

Layout before encryption:

- 30 bits timestamp (seconds from epoch offset ``1730000000``)
- 17 bits node ID
- 17 bits sequence

Generation
----------

.. code-block:: php

   <?php

   use Infocyph\UID\Randflake;

   $id = Randflake::generate(
       nodeId: 42,
       leaseStart: time() - 5,
       leaseEnd: time() + 300,
       secret: 'super-secret-key',
   );

   $idAsBase32Hex = Randflake::generateString(42, time() - 5, time() + 300, 'super-secret-key');

Configuration Object
--------------------

Use ``Infocyph\\UID\\Configuration\\RandflakeConfig``:

- ``nodeId`` (``0..131071``)
- ``leaseStart`` and ``leaseEnd`` (Unix seconds)
- ``secret`` (exactly 16 bytes)
- optional ``sequenceProvider``
- optional ``IdOutputType`` (``STRING``, ``INT``, ``BINARY``)

.. code-block:: php

   <?php

   use Infocyph\UID\Configuration\RandflakeConfig;
   use Infocyph\UID\Enums\IdOutputType;
   use Infocyph\UID\Randflake;

   $config = new RandflakeConfig(
       nodeId: 42,
       leaseStart: time() - 5,
       leaseEnd: time() + 300,
       secret: 'super-secret-key',
       outputType: IdOutputType::STRING,
   );

   $id = Randflake::generateWithConfig($config);

Validation and Parsing
----------------------

.. code-block:: php

   <?php

   use Infocyph\UID\Randflake;

   Randflake::isValid($id);
   $inspect = Randflake::inspect($id, 'super-secret-key');
   $parsed = Randflake::parse($id, 'super-secret-key');

``inspect()`` and ``inspectString()`` output:

- ``timestamp`` (int, Unix seconds)
- ``node_id`` (int)
- ``sequence`` (int)

``parse()`` and ``parseString()`` output:

- ``time`` (DateTimeImmutable)
- ``node_id`` (int)
- ``sequence`` (int)

Binary and Alternate Bases
--------------------------

- ``Randflake::toBytes($id)`` / ``Randflake::fromBytes($bytes)``
- ``Randflake::toBase($id, $base)`` / ``Randflake::fromBase($encoded, $base)``
- ``Randflake::encodeString($id)`` / ``Randflake::decodeString($stringId)``

Supported bases: ``16``, ``32``, ``36``, ``58``, ``62``.

Exception Types
---------------

- ``Infocyph\\UID\\Exceptions\\RandflakeException``
- ``Infocyph\\UID\\Exceptions\\FileLockException``
