Randflake
=========

Class: ``Infocyph\\UID\\Randflake``

Overview
--------

Randflake is a lease-bound 64-bit ID family. ``RandflakeFormat::UID`` remains the
default and preserves UID's legacy reversible Feistel representation.
``RandflakeFormat::UPSTREAM`` uses the pinned upstream SPARX64 representation,
byte order, signed decimal form and Base32hex text contract. Raw IDs do not carry
a format discriminator, so applications using both modes must store one externally.

Layout before permutation:

- 30 bits timestamp (seconds from epoch offset ``1730000000``)
- 17 bits node ID
- 17 bits sequence

The permutation is not authenticated encryption: it does not prove integrity,
authorize access, or replace a standard encryption protocol. Treat Randflake
values as identifiers and enforce authorization independently.

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
- optional ``runtime`` (``GenerationContext``)
- ``format`` (UID legacy by default)
- optional ``leaseEndExclusive`` for upstream mode

.. code-block:: php

   <?php

   use Infocyph\UID\Configuration\RandflakeConfig;
   use Infocyph\UID\Randflake;

   $config = new RandflakeConfig(
       nodeId: 42,
       leaseStart: time() - 5,
       leaseEnd: time() + 300,
       secret: 'super-secret-key',
   );

   $id = Randflake::generateWithConfig($config);

   $upstream = Randflake::generateWithConfig(
       new RandflakeConfig(
           nodeId: 42,
           leaseStart: time() - 5,
           leaseEnd: time() + 300,
           secret: 'super-secret-key',
           format: \Infocyph\UID\Enums\RandflakeFormat::UPSTREAM,
           leaseEndExclusive: time() + 301,
       ),
   );

Lease Semantics
---------------

UID legacy mode keeps the existing inclusive ``leaseEnd`` contract. Upstream
mode uses an exclusive end. If ``leaseEndExclusive`` is omitted,
``RandflakeConfig`` translates the inclusive value to ``leaseEnd + 1``. Every
resampled retry revalidates lease, lifetime and rollback constraints before a
new allocation is consumed.

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

Supported bases: ``16``, ``32``, ``36``, ``58``, ``62``. Pass the same explicit
format to conversion/parsing APIs that was used to generate the ID. In upstream
mode base 32 follows the upstream Base32hex representation.

Exception Types
---------------

- ``Infocyph\\UID\\Exceptions\\RandflakeException``
- ``Infocyph\\UID\\Exceptions\\FileLockException``
