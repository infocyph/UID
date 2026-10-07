Sequence Providers and Coordination
===================================

Snowflake, Sonyflake, Randflake, and sequenced TBSL use a sequence provider.
The filesystem provider is the cross-process default. The in-memory provider is
process-local and must not be used when multiple workers share an ID domain.

Filesystem Provider
-------------------

Use an application-owned directory that is not writable by unrelated local users.
UID rejects symlink/non-regular sequence files, verifies ownership and opened-file
identity, and keeps a stable inode while writers coordinate. Lock acquisition is
bounded; with a Runwire-bound GenerationContext retries use cooperative sleep.

.. code-block:: php

   <?php

   use Infocyph\UID\Snowflake;

   Snowflake::useFilesystemSequenceProvider(
       baseDirectory: '/run/my-app',
       namespace: 'billing',
       lockTimeoutMicros: 250_000,
       reservationSize: 8,
   );

Larger reservations reduce lock traffic by allocating disjoint local ranges.
A process that exits can leave gaps, which is acceptable; reserved values are
never recycled. Reservation metadata is bounded. The default storage location is
unchanged in 6.0, so no automatic state-path migration is performed.

Filesystem state is crash-resistant only to the guarantees of the underlying
filesystem. fflush() is not a power-loss durability guarantee. Deployments that
require stronger durability should use an authoritative external allocator.

PSR-16 Provider
---------------

Generic PSR-16 storage is usable only when its operational contract is strong
enough for sequence allocation. For a shared ID domain:

- the sequence key must remain authoritative while its timestamp can still emit;
- configure the cache so the key is not evicted or expired unexpectedly;
- preserve state across cache clearing, failover, restore and worker restarts;
- coordinate all hosts with an application-supplied distributed synchronizer;
- keep machine/node ownership stable across writers.

UID writes sequence state without an explicit TTL. PSR-16 implementations may
apply their configured default lifetime when null/default TTL is used, so the
backend must be configured accordingly. UID fails closed when state that this
provider instance has already observed disappears or regresses, but that local
guard cannot reconstruct state lost before a new process starts.

The fallback cache lock coordinates only cooperating processes that see the same
local filesystem. It is not a distributed lock, and a Runwire mutex is not a
replacement for distributed synchronization or an authoritative allocation store.

Provider Contract
-----------------

setSequenceProvider() accepts any SequenceProviderInterface:

.. code-block:: php

   public function next(string $type, int $machineId, int $timestamp): int;

The provider returns a positive allocation starting at 1. Generators map that
allocation to their encoded zero-based sequence field where required.

Static provider selectors are process/worker configuration. They are not
request-local storage. In persistent workers, prefer explicitly configured
provider instances on generator config objects when different requests can belong
to different allocation domains.
