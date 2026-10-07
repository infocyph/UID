Database Storage
================

Storage recommendations are documentation only; UID has no runtime ``DbStorage`` API.

.. list-table::
   :header-rows: 1

   * - Format
     - MySQL
     - PostgreSQL
   * - UUID
     - ``BINARY(16)``
     - native ``UUID``
   * - TypeID
     - text, or ``BINARY(16)`` plus type
     - text, or ``UUID`` plus type
   * - ULID
     - ``CHAR(26)`` / ``BINARY(16)``
     - ``CHAR(26)`` / ``BYTEA``
   * - ObjectID
     - ``CHAR(24)`` / ``BINARY(12)``
     - ``CHAR(24)`` / ``BYTEA``
   * - Snowflake/Sonyflake
     - ``BIGINT``
     - ``BIGINT``
   * - Randflake (UID format)
     - ``BIGINT UNSIGNED`` / ``BINARY(8)``
     - ``NUMERIC(20,0)`` / ``BYTEA``
   * - Randflake (upstream format)
     - signed ``BIGINT`` / ``BINARY(8)``
     - signed ``BIGINT`` / ``BYTEA``
   * - TBSL
     - ``CHAR(20)`` / ``BINARY(10)``
     - ``CHAR(20)`` / ``BYTEA``

UUIDv7 is recommended when UUID index locality matters. For TypeID, store the
text prefix only when it is useful at the persistence boundary; otherwise keep
the entity type in the schema and store the underlying UUID bytes.

An epoch is part of a deployed Snowflake or Sonyflake ID domain. Changing it
creates a different domain and may eventually produce values overlapping the
original domain.

Randflake storage must preserve the format discriminator outside the raw value when an application can contain both UID and upstream-compatible representations. The upstream representation is signed 64-bit; the legacy UID representation can require the full unsigned 64-bit domain.
