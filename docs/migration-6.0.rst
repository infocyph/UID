Migrating from UID 5.x to 6.0
==============================

Runtime Requirement
-------------------

UID 6.0 requires PHP 8.4 or newer on a 64-bit runtime and requires ext-ctype.
Applications that must remain on PHP 8.2/8.3 should stay on the 5.x line.

Mixed-ID Ordering
-----------------

IdComparator now defines a total mixed order: digit-only IDs sort numerically
before textual IDs, while textual IDs sort lexically. Numeric-only and text-only
ordering retain their previous semantics. Re-run application tests that relied on
previously ambiguous mixed numeric/text comparisons.

Immutable Epochs
----------------

Snowflake and Sonyflake custom epochs are normalized when their config object is
constructed. Mutating a caller-owned DateTime afterwards no longer changes the
active ID domain. Construct a new config to select a different epoch, and retain
the original epoch metadata when parsing existing rows.

Sequence State
--------------

Use an application-owned filesystem directory for coordinated local allocation.
If an application chooses to move an existing sequence directory, stop all
writers and migrate the existing high-water-mark files as one coordinated
operation before starting writers on the new path. Never run old and new empty
stores simultaneously for the same node/domain.

For PSR-16 coordination, configure non-evicting/non-expiring authoritative state
and distributed locking where multiple hosts share the domain. Cache clearing,
failover or restoration must not reset a live allocation high-water mark.

Sonyflake Formats
-----------------

SonyflakeFormat::UID remains the default and preserves existing UID 39/16/8
time/machine/sequence stored values. SonyflakeFormat::UPSTREAM uses the upstream
time/sequence/machine wire layout and upstream default epoch.

Do not guess a format from an unlabelled integer. Persist format and epoch metadata
with data that may contain both forms. Existing rows remain UID format unless the
application already stored independent format metadata.

Randflake Formats and Leases
----------------------------

RandflakeFormat::UID remains the default and preserves UID's legacy Feistel
representation and inclusive leaseEnd. RandflakeFormat::UPSTREAM uses SPARX64,
upstream byte order and signed-decimal/Base32hex representation.

For upstream mode, leaseEndExclusive is the explicit exclusive boundary. If it is
omitted, RandflakeConfig translates the legacy inclusive leaseEnd to leaseEnd + 1.
Store the format discriminator externally; the same raw bytes can be interpreted
under different format contracts.

Do not rewrite existing IDs merely to adopt an upstream format. A safe migration
keeps old rows with legacy metadata, enables explicit dual-format reads, and
selects the desired format only for new writes after all writers agree on the new
coordination domain. Foreign-key/reference migrations, if desired, belong to the
consuming application and must be transactional.

Optional Runtime and Clock Integration
--------------------------------------

Runwire and PSR-20 clock support are opt-in. Existing synchronous generation
continues to work without either package at runtime. When using Runwire, create
RunwireBinding from the host-owned runtime/request/scope instances and pass it
through GenerationContext; UID never owns the host lifecycle.

Static sequence-provider selectors remain process/worker scoped. Do not use them
as request-local configuration in concurrent persistent workers.

Built-in filesystem and PSR-16 providers receive the config's context for each
allocation without storing it. Cancellation stops both fresh and reserved
allocation before mutation. Provider defaults, operation limits and host deadlines
combine using the earliest applicable wait limit.

Injected generation clocks must produce non-negative timestamps that fit in
64-bit integer microseconds. TBSL rollback state is scoped to provider and machine,
so separate ID domains can use independent clocks. Existing TBSL bytes remain
unchanged; parsing also preserves eleven-digit Unix seconds within its 60-bit field.
ULID and UUIDv7 rollover waits fail after one second or at timestamp exhaustion.

Security Boundary
-----------------

Identifiers are not authorization credentials. Randflake field obfuscation,
including its legacy permutation and upstream-compatible mode, does not
authenticate an ID. Continue enforcing authorization independently and avoid
logging secret-bearing configuration.
