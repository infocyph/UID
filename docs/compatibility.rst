Compatibility Matrix
====================

UUID Support
------------

- ``v1``, ``v3``, ``v4``, ``v5``: RFC 4122 / RFC 9562 compatible layouts.
- ``v6``, ``v7``: RFC 9562 time-ordered UUIDs.
- ``v8``: custom payload strategy inside UUID v8 envelope.
- ``guid()``: Microsoft-compatible GUID text formatting helper.

Non-UUID Families
-----------------

- ULID: Crockford Base32 ULID with monotonic and random modes.
- Snowflake: 64-bit Twitter-style ID (41/5/5/12).
- Sonyflake: 64-bit Sonyflake-style ID (39/16/8).
- TBSL: project-specific time-based sortable hex identifier.
- NanoID and CUID2: URL-safe random IDs.
- KSUID and XID: sortable short ID families.

Binary and Alternate Encodings
------------------------------

- UUID / ULID / TBSL: ``toBytes()`` / ``fromBytes()``.
- UUID / ULID / Snowflake / Sonyflake / TBSL: ``toBase()`` / ``fromBase()``.
- KSUID / XID: ``toBytes()`` / ``fromBytes()``.

Runtime Requirements
--------------------

- Minimum PHP: ``8.2``
- Required extension: ``ext-bcmath``
- Optional sequence backends: filesystem, in-memory, PSR-16 cache, callback.
