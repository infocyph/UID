Database Storage
================

UID includes ``Infocyph\\UID\\DbStorage`` with recommendations for UUID, ULID, and Snowflake.

UUID
----

- MySQL: prefer ``BINARY(16)`` for compact indexes.
- PostgreSQL: prefer native ``UUID`` type.
- Ordering: ``UUIDv7`` provides better insertion locality than ``UUIDv4``.

ULID
----

- MySQL: ``CHAR(26)`` (readable) or ``BINARY(16)`` (compact/index-friendly).
- PostgreSQL: ``CHAR(26)`` or ``BYTEA`` depending on interoperability.
- Ordering: canonical ULID text is chronologically sortable.

Snowflake and Sonyflake
-----------------------

- MySQL: ``BIGINT UNSIGNED``.
- PostgreSQL: ``BIGINT`` if range is safe, otherwise ``NUMERIC(20,0)``.
- Ordering: numeric sort equals time sort.

TBSL
----

- Use ``CHAR(20)`` for canonical uppercase hex.
- Use ``BINARY(10)`` when compactness matters.

Programmatic Access
-------------------

.. code-block:: php

   <?php

   use Infocyph\UID\DbStorage;

   $uuidAdvice = DbStorage::uuid();
   $ulidAdvice = DbStorage::ulid();
   $snowflakeAdvice = DbStorage::snowflake();
