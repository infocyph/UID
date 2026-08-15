ObjectID
========

``ObjectID`` implements the BSON ObjectID layout: a four-byte big-endian Unix
timestamp, five process-random bytes, and a three-byte counter.

.. code-block:: php

   <?php

   use Infocyph\UID\ObjectID;

   $id = ObjectID::generate();
   $bytes = ObjectID::toBytes($id);
   $same = ObjectID::fromBytes($bytes);
   $parts = ObjectID::parse($id);

Canonical text is exactly 24 lowercase hexadecimal characters. The process-random
value and counter are reseeded after a fork, and the counter wraps modulo 24 bits.
