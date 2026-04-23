TBSL
====

Class: ``Infocyph\\UID\\TBSL``

TBSL is a project-specific, time-based, lexicographically sortable uppercase hex ID.

Format
------

- 20 hex chars (10 bytes)
- ``TBSL::isValid()`` verifies ``^[0-9A-F]{20}$``

Generation
----------

.. code-block:: php

   <?php

   use Infocyph\UID\TBSL;

   $id = TBSL::generate();
   $idWithMachine = TBSL::generate(machineId: 9);
   $idWithSequence = TBSL::generate(machineId: 9, sequenced: true);

Configuration Object
--------------------

Use ``Infocyph\\UID\\Configuration\\TBSLConfig`` for:

- fixed or callback-resolved machine ID
- toggling ``sequenced`` mode
- custom sequence provider
- clock-backward policy
- output type (string/int/binary)

.. code-block:: php

   <?php

   use Infocyph\UID\Configuration\TBSLConfig;
   use Infocyph\UID\TBSL;

   $config = new TBSLConfig(machineId: 9, sequenced: true);
   $id = TBSL::generateWithConfig($config);

Parsing
-------

.. code-block:: php

   <?php

   use Infocyph\UID\TBSL;

   $parsed = TBSL::parse($id);

``parse()`` output:

- ``isValid`` (bool)
- ``time`` (DateTimeImmutable|null)
- ``machineId`` (int|null)

Binary and Alternate Bases
--------------------------

- ``TBSL::toBytes($id)`` / ``TBSL::fromBytes($bytes)``
- ``TBSL::toBase($id, $base)`` / ``TBSL::fromBase($encoded, $base)``

Supported bases: ``16``, ``32``, ``36``, ``58``, ``62``.

Exception Type
--------------

TBSL APIs throw ``Infocyph\\UID\\Exceptions\\UIDException`` for validation/runtime issues.
