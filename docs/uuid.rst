UUID
====

Class: ``Infocyph\\UID\\UUID``

Supported Versions
------------------

- ``v1``: time-based
- ``v3``: namespace + MD5
- ``v4``: random
- ``v5``: namespace + SHA-1
- ``v6``: reordered time-based
- ``v7``: Unix millisecond time-ordered
- ``v8``: custom payload within UUID v8 envelope

Generation
----------

.. code-block:: php

   <?php

   use DateTimeImmutable;
   use Infocyph\UID\UUID;

   $v1 = UUID::v1();
   $v3 = UUID::v3('url', 'https://example.com');
   $v4 = UUID::v4();
   $v5 = UUID::v5('dns', 'example.com');
   $v6 = UUID::v6();
   $v7 = UUID::v7();
   $v7AtTime = UUID::v7(new DateTimeImmutable('2026-01-01 00:00:00'));
   $v8 = UUID::v8();

Node-Aware Versions
-------------------

Versions ``v1``, ``v6``, ``v7``, and ``v8`` accept an optional node.
If omitted, UID generates one.

.. code-block:: php

   <?php

   use Infocyph\UID\UUID;

   $node = UUID::getNode(); // 12 hex chars

   $uuid = UUID::v7(null, $node);

Canonical Utilities
-------------------

- ``UUID::normalize($uuid)``
- ``UUID::compact($uuid)``
- ``UUID::toUrn($uuid)``
- ``UUID::toBraces($uuid)``
- ``UUID::lowercase($uuid)``
- ``UUID::uppercase($uuid)``

NIL and MAX
-----------

- ``UUID::nil()``
- ``UUID::max()``
- ``UUID::isNil($uuid)``
- ``UUID::isMax($uuid)``

Validation and Parsing
----------------------

.. code-block:: php

   <?php

   use Infocyph\UID\UUID;

   $ok = UUID::isValid($uuid);
   $parts = UUID::parse($uuid);

``UUID::parse()`` returns:

- ``isValid`` (bool)
- ``version`` (int|null)
- ``variant`` (string|null)
- ``time`` (DateTimeInterface|null)
- ``node`` (string|null)
- ``tail`` (string|null)

For ``v7`` and ``v8``, ``node`` is intentionally ``null``.

Binary and Alternate Bases
--------------------------

- ``UUID::toBytes($uuid)`` / ``UUID::fromBytes($bytes)``
- ``UUID::toBase($uuid, $base)`` / ``UUID::fromBase($encoded, $base)``

Supported bases: ``16``, ``32``, ``36``, ``58``, ``62``.

GUID Helper
-----------

``UUID::guid(bool $trim = true)`` generates GUID-format text.

Exception Type
--------------

UUID-specific failures throw ``Infocyph\\UID\\Exceptions\\UUIDException``.
