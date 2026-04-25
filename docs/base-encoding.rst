Base Encoding
=============

UID exposes base conversion through algorithm-specific APIs and a shared
``Infocyph\\UID\\Support\\BaseEncoder`` utility.

Supported Bases
---------------

- ``16``: lowercase hexadecimal alphabet
- ``32``: ``0-9a-v`` alphabet
- ``36``: ``0-9a-z`` alphabet
- ``58``: Bitcoin-style Base58 alphabet
- ``62``: ``0-9A-Za-z`` alphabet

Algorithm APIs
--------------

Prefer the algorithm-specific methods when converting IDs, because they validate
the canonical input and restore the canonical output type:

- ``UUID::toBase($uuid, $base)`` / ``UUID::fromBase($encoded, $base)``
- ``ULID::toBase($ulid, $base)`` / ``ULID::fromBase($encoded, $base)``
- ``Snowflake::toBase($id, $base)`` / ``Snowflake::fromBase($encoded, $base)``
- ``Sonyflake::toBase($id, $base)`` / ``Sonyflake::fromBase($encoded, $base)``
- ``TBSL::toBase($id, $base)`` / ``TBSL::fromBase($encoded, $base)``

.. code-block:: php

   <?php

   use Infocyph\UID\UUID;

   $uuid = UUID::v7();
   $base58 = UUID::toBase($uuid, 58);
   $canonical = UUID::fromBase($base58, 58);

Low-Level Encoder
-----------------

``BaseEncoder`` works with raw bytes:

.. code-block:: php

   <?php

   use Infocyph\UID\Support\BaseEncoder;

   $bytes = random_bytes(16);
   $encoded = BaseEncoder::encodeBytes($bytes, 62);
   $decoded = BaseEncoder::decodeToBytes($encoded, 62, 16);

``decodeToBytes()`` requires the expected byte length so the decoded value can be
left-padded and validated consistently.

Notes
-----

Alternate-base values are transport encodings. They do not replace each
algorithm's canonical representation.
