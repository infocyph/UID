ULID
====

Class: ``Infocyph\\UID\\ULID``

ULID uses Crockford Base32 and produces 26-character sortable identifiers.

Generation Modes
----------------

UID supports explicit generation modes via ``UlidGenerationMode``:

- ``MONOTONIC`` (default)
- ``RANDOM``

.. code-block:: php

   <?php

   use Infocyph\UID\Enums\UlidGenerationMode;
   use Infocyph\UID\ULID;

   $default = ULID::generate();
   $monotonic = ULID::generateMonotonic();
   $random = ULID::generateRandom();
   $explicit = ULID::generate(mode: UlidGenerationMode::RANDOM);

Validation and Time Extraction
------------------------------

.. code-block:: php

   <?php

   use Infocyph\UID\ULID;

   $ok = ULID::isValid($ulid);
   $time = ULID::getTime($ulid); // DateTimeImmutable

Binary and Alternate Bases
--------------------------

- ``ULID::toBytes($ulid)`` / ``ULID::fromBytes($bytes)``
- ``ULID::toBase($ulid, $base)`` / ``ULID::fromBase($encoded, $base)``

Supported bases: ``16``, ``32``, ``36``, ``58``, ``62``.

Exception Type
--------------

ULID-specific failures throw ``Infocyph\\UID\\Exceptions\\ULIDException``.
