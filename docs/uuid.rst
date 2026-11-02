UUID
====

Class: ``Infocyph\\UID\\UUID``

Supported Versions
------------------

- ``v1``: time-based
- ``v2``: DCE Security with an embedded local identifier and domain
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
   $v2 = UUID::v2(localIdentifier: 1001);
   $v3 = UUID::v3('url', 'https://example.com');
   $v4 = UUID::v4();
   $v5 = UUID::v5('dns', 'example.com');
   $v6 = UUID::v6();
   $v7 = UUID::v7();
   $v7AtTime = UUID::v7(new DateTimeImmutable('2026-01-01 00:00:00'));
   $v8 = UUID::v8();

DCE Security (v2)
-----------------

``UUID::v2($localIdentifier, $localDomain, $node, $clockSequence)`` requires an
unsigned 32-bit local identifier (``0..4294967295``). The domain defaults to
``UUID::DCE_DOMAIN_PERSON`` (``0``); ``DCE_DOMAIN_GROUP`` (``1``) and
``DCE_DOMAIN_ORG`` (``2``) are also supported. UID does not discover account IDs.

A null node generates a fresh random multicast node for each call. A null clock
sequence selects a random value in ``0..63``. Explicit nodes require an explicit
clock sequence. For fixed nodes, the caller must coordinate sequences across
processes, forks and restarts: reuse of a node/domain/identifier/sequence within
one approximately 429.5-second timestamp bucket repeats the UUID. Only 64
sequences fit in each bucket. Default random nodes provide probabilistic
uniqueness; enforce storage uniqueness where required.

``UUID::parse()`` adds integer ``local_domain`` and ``local_identifier`` fields
for v2 only. Its ``time`` is the beginning of the coarse timestamp bucket,
not the exact creation time. The same behavior is exposed by ``UuidValue``.

.. code-block:: php

   $person = UUID::v2(1001);
   $group = UUID::v2(100, UUID::DCE_DOMAIN_GROUP);
   $coordinated = UUID::v2(1001, UUID::DCE_DOMAIN_PERSON, 'abcdef123456', 42);

The facade ``Id::uuid2()`` and namespaced ``uuid2()`` helper accept the same
arguments. See the `DCE UUID v2 guide <https://uuid.ramsey.dev/en/stable/rfc4122/version2.html>`_.

Node-Aware Versions
-------------------

Versions ``v1``, ``v2``, ``v6``, and ``v8`` accept an optional node. If omitted,
UID generates one. For v2, a fixed node also requires an explicit clock sequence.
UUIDv7 is timestamp/randomness based and does not accept a node.

.. code-block:: php

   <?php

   use Infocyph\UID\UUID;

   $node = UUID::getNode(); // 12 hex chars

   $v1 = UUID::v1($node);
   $v6 = UUID::v6($node);
   $v8 = UUID::v8($node);

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

Validation accepts the RFC 9562 NIL and MAX values and UUID versions ``1..8``
using the RFC variant. UUIDv7 rejects timestamps outside its unsigned 48-bit
millisecond field instead of truncating them.

.. code-block:: php

   <?php

   use Infocyph\UID\UUID;

   $ok = UUID::isValid($uuid);
   $parts = UUID::parse($uuid);

``UUID::parse()`` returns:

- ``version`` (int|null)
- ``variant`` (string|null)
- ``time`` (DateTimeInterface|null)
- ``node`` (string|null)
- ``tail`` (string|null)

For ``v7`` and ``v8``, ``node`` is intentionally ``null``.

Versions ``v1`` and ``v6`` preserve timestamps before the Unix epoch, including
fractional seconds. Their 100-nanosecond fields are truncated to PHP's microsecond
precision before conversion.

Binary and Alternate Bases
--------------------------

- ``UUID::toBytes($uuid)`` / ``UUID::fromBytes($bytes)``
- ``UUID::toBase($uuid, $base)`` / ``UUID::fromBase($encoded, $base)``

Supported bases: ``10``, ``16``, ``32``, ``36``, ``58``, ``62``.

GUID Helper
-----------

``UUID::guid(bool $trim = true)`` generates GUID-format text.

Exception Type
--------------

UUID-specific failures throw ``Infocyph\\UID\\Exceptions\\UUIDException``.
