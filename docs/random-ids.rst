Random and Short IDs
====================

NanoID and CUID2
----------------

Class: ``Infocyph\\UID\\RandomId``

.. code-block:: php

   <?php

   use Infocyph\UID\RandomId;

   $nano = RandomId::nanoId(21);
   $isNano = RandomId::isNanoId($nano, 21);
   $nanoInfo = RandomId::parseNanoId($nano, 21);

   $cuid2 = RandomId::cuid2(24);
   $isCuid2 = RandomId::isCuid2($cuid2);
   $cuid2Info = RandomId::parseCuid2($cuid2);

Validation/parsing outputs:

- ``parseNanoId()``: ``['isValid', 'length', 'alphabet']``
- ``parseCuid2()``: ``['isValid', 'length']``

KSUID
-----

Class: ``Infocyph\\UID\\KSUID``

- fixed-length 27-char Base62 string
- sortable by timestamp prefix

.. code-block:: php

   <?php

   use Infocyph\UID\KSUID;

   $id = KSUID::generate();
   $ok = KSUID::isValid($id);
   $parts = KSUID::parse($id);  // ['isValid', 'time', 'payload']

   $bytes = KSUID::toBytes($id);
   $same = KSUID::fromBytes($bytes);

XID
---

Class: ``Infocyph\\UID\\XID``

- fixed-length 20-char Base32 lowercase string
- includes timestamp, machine, pid, counter

.. code-block:: php

   <?php

   use Infocyph\UID\XID;

   $id = XID::generate();
   $ok = XID::isValid($id);
   $parts = XID::parse($id);  // ['isValid', 'time', 'machine', 'pid', 'counter']

   $bytes = XID::toBytes($id);
   $same = XID::fromBytes($bytes);

Opaque IDs
----------

Class: ``Infocyph\\UID\\OpaqueId``

- ``OpaqueId::random($length)`` for short public IDs
- ``OpaqueId::fromInt($value, $salt)`` and ``OpaqueId::toInt($token, $salt)`` for reversible obfuscation

.. code-block:: php

   <?php

   use Infocyph\UID\OpaqueId;

   $token = OpaqueId::fromInt(123456, 'app-secret-salt');
   $value = OpaqueId::toInt($token, 'app-secret-salt');

Deterministic IDs
-----------------

Class: ``Infocyph\\UID\\DeterministicId``

``DeterministicId::fromPayload($payload, $length = 24, $namespace = 'default')``
creates deterministic Base62 output from payload + namespace.

.. code-block:: php

   <?php

   use Infocyph\UID\DeterministicId;

   $id = DeterministicId::fromPayload('user:42', 24, 'users');
