Quickstart
==========

Using the ``Id`` Facade
-----------------------

.. code-block:: php

   <?php

   use Infocyph\UID\Id;

   $uuid = Id::uuid();          // default UUID strategy (v7)
   $ulid = Id::ulid();
   $snowflake = Id::snowflake();
   $sonyflake = Id::sonyflake();
   $tbsl = Id::tbsl();
   $nano = Id::nanoId(21);
   $cuid2 = Id::cuid2(24);

Validation and Parsing
----------------------

.. code-block:: php

   <?php

   use Infocyph\UID\Id;

   Id::uuidIsValid($uuid);          // bool
   Id::uuidParse($uuid);            // ['isValid', 'version', 'variant', 'time', 'node', 'tail']

   Id::nanoIdIsValid($nano, 21);    // bool
   Id::nanoIdParse($nano, 21);      // ['isValid', 'length', 'alphabet']

   Id::cuid2IsValid($cuid2);        // bool
   Id::cuid2Parse($cuid2);          // ['isValid', 'length']

Binary and Base Conversion
--------------------------

.. code-block:: php

   <?php

   use Infocyph\UID\Id;

   $bytes = Id::uuidToBytes($uuid);       // 16 bytes
   $roundTrip = Id::uuidFromBytes($bytes);

   $base58 = Id::uuidToBase($uuid, 58);
   $uuidAgain = Id::uuidFromBase($base58, 58);

Configuration-Based Generators
------------------------------

.. code-block:: php

   <?php

   use Infocyph\UID\Configuration\SnowflakeConfig;
   use Infocyph\UID\Enums\ClockBackwardPolicy;
   use Infocyph\UID\Enums\IdOutputType;
   use Infocyph\UID\Id;

   $config = new SnowflakeConfig(
       datacenterId: 1,
       workerId: 7,
       clockBackwardPolicy: ClockBackwardPolicy::WAIT,
       outputType: IdOutputType::STRING,
   );

   $id = Id::snowflake($config);
