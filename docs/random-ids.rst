Random and Compact IDs
======================

RandomId and NanoID
-------------------

``RandomId`` and ``NanoID`` use rejection sampling over PHP's CSPRNG, avoiding
modulo bias for every valid single-byte alphabet size from 2 through 256.
Lengths are limited to 1024 bytes. Alphabets must contain unique symbols.

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
