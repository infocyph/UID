Global Helper Functions
=======================

UID autoloads helper functions from ``src/functions.php``.

UUID Helpers
------------

- ``uuid1(?string $node = null)``
- ``uuid3(string $namespace, string $string)``
- ``uuid4()``
- ``uuid5(string $namespace, string $string)``
- ``uuid6(?string $node = null)``
- ``uuid7(?DateTimeInterface $dateTime = null, ?string $node = null)``
- ``uuid8(?string $node = null)``
- ``guid(bool $trim = true)``

UUID Utility Helpers
--------------------

- ``uuid_nil()``, ``uuid_max()``
- ``uuid_is_nil(string $uuid)``, ``uuid_is_max(string $uuid)``
- ``uuid_normalize(string $uuid)``, ``uuid_compact(string $uuid)``
- ``uuid_urn(string $uuid)``, ``uuid_braces(string $uuid)``
- ``uuid_to_base(string $uuid, int $base)``, ``uuid_from_base(string $encoded, int $base)``

ULID Helpers
------------

- ``ulid(?DateTimeInterface $dateTime = null)``
- ``ulid_monotonic(?DateTimeInterface $dateTime = null)``
- ``ulid_random(?DateTimeInterface $dateTime = null)``
- ``ulid_to_base(string $ulid, int $base)``
- ``ulid_from_base(string $encoded, int $base)``

Snowflake/Sonyflake/TBSL Helpers
--------------------------------

- ``snowflake(int $datacenter = 0, int $workerId = 0)``
- ``snowflake_is_valid(string $id)``
- ``snowflake_to_base(string $id, int $base)``
- ``snowflake_from_base(string $encoded, int $base)``

- ``sonyflake(int $machineId = 0)``
- ``sonyflake_is_valid(string $id)``
- ``sonyflake_to_base(string $id, int $base)``
- ``sonyflake_from_base(string $encoded, int $base)``

- ``tbsl(int $machineId = 0, bool $sequenced = false)``
- ``tbsl_is_valid(string $id)``
- ``tbsl_to_base(string $id, int $base)``
- ``tbsl_from_base(string $encoded, int $base)``

Short/Random/Opaque Helpers
---------------------------

- ``nanoid(int $size = 21)``
- ``nanoid_is_valid(string $id, ?int $size = null)``
- ``cuid2(int $maxLength = 24)``
- ``cuid2_is_valid(string $id)``
- ``opaque_id(int $length = 12)``
- ``deterministic_id(string $payload, int $length = 24, string $namespace = 'default')``

Other Helpers
-------------

- ``ksuid(?DateTimeInterface $dateTime = null)``
- ``xid()``

Example
-------

.. code-block:: php

   <?php

   $id = uuid7();
   $ul = ulid_random();
   $sf = snowflake(1, 2);
   $short = nanoid(16);
