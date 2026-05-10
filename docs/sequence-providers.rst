Sequence Providers
==================

UID supports pluggable sequence backends for Snowflake, Sonyflake, and sequenced TBSL.

Supported Providers
-------------------

- ``FilesystemSequenceProvider`` (default)
- ``InMemorySequenceProvider``
- ``PsrSimpleCacheSequenceProvider``
- ``CallbackSequenceProvider``
- Any custom ``SequenceProviderInterface`` implementation

Per-Algorithm Static Selection
------------------------------

Each algorithm class using ``GetSequence`` provides these static methods:

- ``setSequenceProvider(SequenceProviderInterface $provider)``
- ``resetSequenceProvider()``
- ``useFilesystemSequenceProvider(?string $baseDirectory = null, int $waitTime = 1000, int $maxAttempts = 1000)``
- ``useInMemorySequenceProvider()``
- ``useSimpleCacheSequenceProvider(CacheInterface $cache, string $prefix = 'uid:seq:', int $waitTime = 1000, int $maxAttempts = 1000)``
- ``useSequenceCallback(callable $callback)``

Defaults are tuned for better contention tolerance in parallel workloads.
If you prefer faster fail behavior, set lower ``waitTime``/``maxAttempts`` explicitly.

Example: Process-Local In-Memory
--------------------------------

.. code-block:: php

   <?php

   use Infocyph\UID\Snowflake;

   Snowflake::useInMemorySequenceProvider();
   $id = Snowflake::generate(1, 1);

Example: PSR-16 Simple Cache
----------------------------

.. code-block:: php

   <?php

   use Infocyph\UID\Sequence\PsrSimpleCacheSequenceProvider;
   use Infocyph\UID\Snowflake;
   use Psr\SimpleCache\CacheInterface;

   /** @var CacheInterface $cache */
   $provider = new PsrSimpleCacheSequenceProvider($cache, 'uid:seq:');
   Snowflake::setSequenceProvider($provider);

Example: External Synchronizer
------------------------------

``PsrSimpleCacheSequenceProvider`` optionally accepts a synchronizer callback:

.. code-block:: php

   <?php

   $provider = new PsrSimpleCacheSequenceProvider(
       cache: $cache,
       synchronizer: function (string $key, callable $criticalSection): mixed {
           // Acquire distributed lock here (Redis, DB, etc.)
           // then run and return the critical section result.
           return $criticalSection();
       }
   );

   Snowflake::setSequenceProvider($provider);

Custom Provider Contract
------------------------

Implement:

.. code-block:: php

   public function next(string $type, int $machineId, int $timestamp): int;

The return value should be a positive integer sequence.
