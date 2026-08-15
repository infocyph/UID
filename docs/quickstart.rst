Quickstart
==========

.. code-block:: php

   <?php

   use Infocyph\UID\Id;
   use Infocyph\UID\TypeID;
   use Infocyph\UID\UUID;

   $uuid = Id::uuid();
   $ulid = Id::ulid();
   $typeId = Id::typeId('user');
   $objectId = Id::objectId();
   $random = Id::random(24);

   $uuidParts = UUID::parse($uuid);
   $typeParts = TypeID::parse($typeId);
   $uuidBytes = UUID::toBytes($uuid);
   $sameUuid = UUID::fromBytes($uuidBytes);

Configuration-based coordinated generators return canonical strings. Output
representation is handled separately with ``toBytes()`` or ``toBase()``.

.. code-block:: php

   <?php

   use Infocyph\UID\Configuration\SnowflakeConfig;
   use Infocyph\UID\Enums\ClockBackwardPolicy;
   use Infocyph\UID\Id;

   $config = new SnowflakeConfig(
       datacenterId: 1,
       workerId: 7,
       clockBackwardPolicy: ClockBackwardPolicy::WAIT,
   );

   $id = Id::snowflake($config);
