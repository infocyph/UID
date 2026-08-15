Sequence Providers and Coordination
===================================

Snowflake, Sonyflake, Randflake, and sequenced TBSL use a sequence provider.
The filesystem provider is the cross-process default. The in-memory provider is
process-local and must not be used when multiple workers share an ID domain.

Filesystem Provider
-------------------

The normal path uses blocking ``flock()``. An optional monotonic timeout can be
set in microseconds. State reads are bounded, malformed state fails closed, and
validated paths—not open handles—are cached. Namespaces isolate applications
sharing a directory.

.. code-block:: php

   <?php

   use Infocyph\UID\Snowflake;

   Snowflake::useFilesystemSequenceProvider(
       baseDirectory: '/run/my-app',
       namespace: 'billing',
       lockTimeoutMicros: 250_000,
       reservationSize: 8,
   );

Reservation size defaults to 1. Larger ranges reduce lock traffic but reserve
unused values when a process exits; they do not permit duplicate allocations.

Other Providers
---------------

``setSequenceProvider()`` accepts any ``SequenceProviderInterface``. Convenience
methods select filesystem, in-memory, callback, or optional PSR-16 providers.
The PSR-16 provider needs an application-supplied distributed synchronizer when
the cache is shared by multiple hosts; its fallback lock coordinates one host only.

The provider contract is:

.. code-block:: php

   public function next(string $type, int $machineId, int $timestamp): int;

It returns a positive allocation starting at 1. Generators map that allocation
to their zero-based encoded sequence fields.
