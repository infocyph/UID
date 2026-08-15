Id Facade
=========

``Infocyph\UID\Id`` is a focused generation facade. ``Id::uuid()`` defaults to
UUIDv7. Parsing, validation, and conversion remain on each algorithm class.

Generation methods include ``uuid1`` through ``uuid8``, ``ulid``, ``typeId``,
``objectId``, ``snowflake``, ``sonyflake``, ``randflake``, ``tbsl``, ``ksuid``,
``xid``, ``nanoId``, ``cuid2``, ``random``, and ``deterministic``.

``snowflakeValue`` and ``sonyflakeValue`` are the two configuration-aware value
factories. They preserve a configured custom epoch so timestamps are interpreted
in the same ID domain in which they were generated.

.. code-block:: php

   <?php

   use Infocyph\UID\Configuration\SnowflakeConfig;
   use Infocyph\UID\Id;

   $uuid = Id::uuid();
   $typeId = Id::typeId('user');
   $objectId = Id::objectId();
   $random = Id::random(24);

   $config = new SnowflakeConfig(customEpoch: 1_700_000_000_000);
   $value = Id::snowflakeValue($config);
