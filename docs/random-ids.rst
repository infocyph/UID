Random and Compact IDs
======================

RandomId and NanoID
-------------------

``RandomId`` uses rejection sampling over PHP's CSPRNG for caller-supplied
single-byte alphabets, avoiding modulo bias for valid alphabet sizes from 2
through 256. ``NanoID`` uses a fixed Base64url alphabet and derives the requested
length directly from CSPRNG bytes. Generated lengths are capped at 1024.
RandomId alphabets must contain unique symbols.

.. code-block:: php

   <?php

   use Infocyph\UID\NanoID;
   use Infocyph\UID\RandomId;

   $random = RandomId::generate();
   $custom = RandomId::generate(32, '0123456789abcdef');
   $nano = NanoID::generate(21);

Invalid NanoID, CUID2, KSUID, and XID values cause ``parse()`` to throw; successful
parse results contain only format data and do not repeat an ``isValid`` field.

CUID2, KSUID, and XID
---------------------

``CUID2`` produces opaque hashed entropy, ``KSUID`` combines a timestamp with a
random payload, and ``XID`` combines timestamp, machine, PID, and counter data.
All process-local state is refreshed after a fork.

Opaque and Deterministic IDs
----------------------------

``OpaqueId::fromInt()`` and ``toInt()`` provide reversible integer obfuscation;
negative inputs are rejected. This is not encryption or authorization.

``DeterministicId::fromPayload()`` creates a stable Base62 token from a length-
prefixed namespace and payload. It provides stable identity, not secrecy.
