TypeID
======

``TypeID`` combines an optional lowercase type prefix with a canonical 26-character
Base32 encoding of 16 UUID bytes. Generation uses UUIDv7.

.. code-block:: php

   <?php

   use Infocyph\UID\TypeID;

   $id = TypeID::generate('user');
   $uuid = TypeID::toUuid($id);
   $same = TypeID::fromUuid('user', $uuid);
   $parts = TypeID::parse($id);

Prefixes are at most 63 ASCII characters, use lowercase letters and internal
underscores, and must start and end with a letter. The empty prefix is valid and
omits the underscore. Text is canonical lowercase;
ambiguous Crockford characters and values outside 128 bits are rejected.
