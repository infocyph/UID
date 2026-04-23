Id Facade
=========

The ``Infocyph\\UID\\Id`` class is the unified entry point for all generators and helpers.

Default Strategy
----------------

``Id::uuid()`` maps to ``UUID::v7()`` by default.

Core Generation Methods
-----------------------

- ``Id::uuid1()``, ``Id::uuid3()``, ``Id::uuid4()``, ``Id::uuid5()``, ``Id::uuid6()``, ``Id::uuid7()``, ``Id::uuid8()``
- ``Id::ulid()``
- ``Id::snowflake()``
- ``Id::sonyflake()``
- ``Id::tbsl()``
- ``Id::nanoId()``
- ``Id::cuid2()``
- ``Id::ksuid()``
- ``Id::xid()``
- ``Id::opaque()``
- ``Id::deterministic()``

Value Object Helpers
--------------------

The facade also exposes value-object constructors:

- ``Id::uuid1Value()``, ``Id::uuid3Value()``, ..., ``Id::uuid8Value()``
- ``Id::uuidValue($uuid)``
- ``Id::ulidValue()``
- ``Id::snowflakeValue()``
- ``Id::sonyflakeValue()``
- ``Id::tbslValue()``

UUID Utility Helpers via Id
---------------------------

- ``uuidNil()``, ``uuidMax()``, ``uuidIsNil()``, ``uuidIsMax()``, ``uuidIsValid()``
- ``uuidNormalize()``, ``uuidCompact()``, ``uuidBraces()``, ``uuidUrn()``
- ``uuidToBytes()``, ``uuidFromBytes()``
- ``uuidToBase()``, ``uuidFromBase()``
- ``uuidParse()``

NanoID/CUID2 Utility Helpers via Id
-----------------------------------

- ``nanoIdIsValid()``, ``nanoIdParse()``
- ``cuid2IsValid()``, ``cuid2Parse()``

Example
-------

.. code-block:: php

   <?php

   use Infocyph\UID\Id;

   $uuidV7 = Id::uuid();
   $uuidV4 = Id::uuid4();

   $uuidValue = Id::uuidValue($uuidV7);
   $isSortable = $uuidValue->isSortable();
