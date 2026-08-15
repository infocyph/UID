Namespaced Generator Functions
==============================

Composer autoloads generator-only functions in the ``Infocyph\UID`` namespace.
Import them explicitly before use:

.. code-block:: php

   <?php

   use function Infocyph\UID\object_id;
   use function Infocyph\UID\random_id;
   use function Infocyph\UID\type_id;
   use function Infocyph\UID\uuid7;

   $uuid = uuid7();
   $typeId = type_id('user');
   $objectId = object_id();
   $random = random_id(24);

Available helpers are ``uuid1``, ``uuid3``, ``uuid4``, ``uuid5``, ``uuid6``,
``uuid7``, ``uuid8``, ``ulid``, ``type_id``, ``object_id``, ``snowflake``,
``sonyflake``, ``randflake``, ``tbsl``, ``ksuid``, ``xid``, ``nano_id``,
``cuid2``, and ``random_id``.

Conversion, parsing, validation, and internal ``__uid_*`` helpers are deliberately
not exported. Use the relevant class for those operations.
