---
orphan: true
---

# UID 6.0 full improvement and release plan

Review date: 2026-10-06. Reviewed revision: `322c9d9c033b16e4a47ff88c156ce230e3cef0fa`.
Latest local release tag: `5.0`, at `4a95eb8058e73c72e74e44fedd25755198899eae`.
Status: sections A–F implemented and regression-covered; section G, host performance, soak, and exact-final release acceptance remain open.

## Latest gate investigation (2026-10-07)

Rechecked exact committed revision
`d3858dc537d08710502f3222ab9f1155bd7def30`. Security & Standards run
`37588152700` passed. Release Acceptance run `37588152012` passed diagnostics,
the five-minute soak and eleven of twelve host-performance lanes. The single
remaining failed job is `host-performance (snowflake-contended, 5, 1)`:

| Metric | Tag 5.0 | Candidate |
| --- | ---: | ---: |
| Median successful RPM | 259926.06731 | 252941.12415 |
| Trial spread | 0.52804% | 0.87610% |
| p99 latency | 3.489 ms | 3.376 ms |

Both sides were stable and recorded zero failed responses, timeouts and
within-response duplicates. The 2.69% RPM regression exceeds the unchanged 2%
budget. This supersedes the three failed lanes on the previous revision below.

The working-tree fix retains the original lock-wait policy and clears the PHP
file-status cache before both pathname checks without evicting realpath entries.
Fresh `lstat()` ownership/type checks and pre-open/handle/post-open inode matching
still fence stale path resolution. A process fixture replaces a primed parent
directory symlink and checks that allocation either uses the current file or
fails closed, leaving the old target untouched. Cross-process uniqueness
coverage now exercises reservation sizes one and sixteen.

Remove redundant internal provider resolution after generator entry points have
already resolved the provider, skip reservation bookkeeping calls when disabled,
and keep opening/error handling together without an extra wrapper call. The
public provider/generator signatures and passed-context dispatch are unchanged.

System-call profiling of 10,000 allocations reduced `newfstatat` calls from
30,726 to 20,728 while retaining both fresh pathname checks and handle checks.
The [PHP manual](https://www.php.net/manual/en/function.clearstatcache.php)
distinguishes file-status invalidation from optional realpath-cache eviction.
Sustained measurements and final-revision hosted confirmation remain required.

An initial lock-backoff experiment passed sustained concurrency-five RPM but
regressed at concurrency fifty, so it was discarded. Keep the existing 2% budget
and require all affected workloads to pass; one improved lane cannot excuse
a regression in another. Keep this plan until final-revision acceptance closes
the other resource/lifecycle coverage requirements below.

Local PHPForge processors, the detailed suite and final release guard passed on
the selected implementation: 229 tests / 4,094 assertions, zero dependency
advisories, and unchanged static/complexity/security gates.

Final selected-source local host measurements used PHP 8.5.4, OPcache enabled,
JIT disabled, 64 CLI server children, production authoritative autoloaders and
four 60-second trials per release in balanced AB/BA order:

| Workload | Tag 5.0 RPM | Working-copy RPM | Regression | 2% gate |
| --- | ---: | ---: | ---: | --- |
| Snowflake contention, concurrency 5 | 523986.55703 | 516127.45456 | 1.50% | Pass |
| Snowflake contention, concurrency 50 | 641233.69239 | 620850.10493 | 3.18% | Fail |

Both pairs passed result-contract validation and reported stable trials and zero
failed responses, timeouts or within-response duplicates. Baseline/candidate
spreads were 1.44641%/0.87400% at concurrency five and 0.78674%/0.98569% at fifty.
Corresponding p99 values were 2.511/2.529 ms and 13.528/12.547 ms. PHPForge's
unchanged stable-environment comparison passed concurrency five and rejected
concurrency fifty; lower p99 does not excuse the RPM failure.

The committed revision's hosted PHP 8.4 concurrency-fifty lane passed at
184408.87 → 183652.12 RPM (0.41% regression). A separate short local comparison
of exact `d3858dc` production source against the working copy measured
634802.05397 → 630573.07866 RPM (0.67% regression, four ten-second trials per
source). This supporting diagnostic does not certify a sustained 5.0 comparison
or establish that the PHP 8.5 regression is resolved. Keep local and hosted
runtime evidence separate and require exact-final-commit hosted confirmation.

The selected production source also passed the no-optional-package smoke and
strict Sphinx build. Changes remain uncommitted; no release or tag was published.

## 2026-10-07 cross-check

Rechecked committed revision `5f62244d610ae77386d5d193dd86f1780eb9af8b`
and the complete implementation against the acceptance requirements below.
The plan remains because the release gates are not complete.

Additional working-tree corrections and regressions:

- Forward each generator config's `GenerationContext` to built-in filesystem and
  PSR-16 allocation without storing a request binding on the shared provider.
  Contended-lock tests exercise intermediary forwarding, other-task progress and
  host cancellation through this config-only path.
- Check cancellation/completion before fresh and reserved provider allocation,
  after a cache read and immediately before mutation; preserve terminal binding
  failures through synchronizer/error handling.
- Refresh lock pathname metadata rather than trusting PHP's cached `lstat()`
  result. A process replacement fixture verifies rejection of an externally
  replaced path while leaving its target untouched.
- Associate TBSL rollback history weakly with the actual provider and machine,
  preserving independent clocks. Validate its 60-bit timestamp before normal
  allocation and correctly parse eleven-digit Unix seconds.
- Reject Sonyflake epochs in the future even within one 10 ms tick, and reject
  extreme epochs before overflowing integer division.
- Keep clock and wait calculations in the supported integer domain, reject
  overflowing inclusive-to-exclusive Randflake leases, recheck live Randflake
  provider state after reentrant/suspending allocation, and reject times before
  Randflake's epoch before allocation.
- Bound implicit ULID/UUIDv7 rollover waits and fail promptly at timestamp
  exhaustion; require exact UUID node width including end of input.
- Validate host response IDs against the workload format and exclude duplicate
  responses from successful throughput. Protect even-sample median/trial metadata
  behavior with regression coverage.

Hosted Security & Standards run `37584251034` passed on `5f62244`.
Hosted Release Acceptance run `37584250265` passed diagnostics and the existing
five-minute soak, but failed the following required host gates:

| Workload | Baseline RPM | Candidate RPM | Failure |
| --- | ---: | ---: | --- |
| Snowflake contention, concurrency 1 | 136025.70070 | 131210.32508 | 3.54% regression; 2% budget |
| Snowflake contention, concurrency 50 | 556309.78158 | 539240.87060 | 3.07% regression; 2% budget |
| CUID2 single ID, concurrency 50 | 213626.22967 | 387476.98209 | Candidate spread 16.47568%; 15% stability ceiling |

All three downloaded pairs report zero failed responses, timeouts and
within-response duplicates. CUID2's gain does not excuse its unstable trials.
These results certify neither the new working-tree corrections nor a 6.0 release.

The full plan also requires acceptance coverage that the current harness has not
yet supplied: host CPU/peak and steady RSS fields are null; worker readiness is
inferred from request counts rather than verified per worker; HTTP duplicate
detection is within responses rather than across the measured workload; queue,
lock-wait and predefined resource/latency ceilings are absent. The soak exercises
in-memory generation and cancellation, but not contention, released provider
domains or worker replacement. The Runwire profile compares CPU generation only
and does not measure its intended scheduling benefit under contention.
Keep these gates open; do not remove this plan or tag 6.0 until final-revision
evidence closes them without weakening correctness, security or budgets.

Local verification of the corrected source on PHP 8.5.4: PHPForge processors,
the full detailed suite and the final release guard passed. The final guard ran
227 tests / 4,074 assertions and reported zero dependency advisories; the existing
transitive development-only `doctrine/annotations` abandonment remains a warning.
The strict Sphinx build (`-n -W --keep-going`) passed. A clean authoritative
`--no-dev` installation executed native generators, both format modes, value
metadata and codecs with Runwire, PSR-20 and PSR-16 absent. The pathname replacement
regression was independently run with the committed old opener and failed there.

Short paired PHP 8.5.4 diagnostics (four trials of five seconds, not the required
sustained acceptance) on the corrected production source retained zero response
errors, timeouts and within-response duplicates. Snowflake concurrency 1 measured
384004.51513 → 374224.65428 RPM (2.55% regression); concurrency 50 measured
652734.08177 → 641236.76811 RPM (1.76% regression). Both pairs were stable under
the existing spread rule. These short diagnostics leave the required sustained
Snowflake gate open and do not replace exact-final PHP 8.4/8.5 hosted evidence.

This plan follows `vendor/infocyph/phpforge/resources/engineering-principles.md`:
correctness and security precede performance; preserve public contracts and named
arguments; distinguish required changes from optional features; keep dependencies
and abstractions justified; use successful host RPM as the performance criterion;
resolve quality findings at their cause without suppressions or weaker gates.
No production code or dependency constraints were changed during this review.

## Complete planned scope

The requested scope includes every review finding and every previously listed
improvement. Enhancements are included delivery work; optional integrations and
format modes remain optional for consumers. Profiling work must finish with a
measured implementation decision, even when the correct decision is to retain
the existing algorithm. No item is left as an unspecified later wishlist.

Target **6.0.0** for the complete scope. Correcting the public mixed-ID comparison
contract and freezing mutable epoch configuration can change observable behavior;
include those changes in a major release with migration coverage. The next
release requires **PHP 8.4 or newer on a 64-bit runtime**, as requested. Preserve
legacy stored ID formats and include the PHP minimum change in the migration guide.

| Delivery area | Included changes | Implementation section |
| --- | --- | --- |
| Required safety fixes | R01/R02/R08: secure state and locks, authoritative allocation, loss/exhaustion handling | A |
| Required correctness fixes | R03–R07/R09–R11: generation state, counters, validation, lease retries, worker state, numeric codecs, comparison and GUIDs | B |
| Required release hygiene | R12–R14: tooling, support matrix, dependency metadata, docs and secret redaction | C |
| Included runtime enhancement | Passed Runwire 2.1.1 context/request/task instances, capability selection, cooperative waits and lifecycle-safe fallback | D |
| Included format enhancement | Explicit upstream-compatible Sonyflake/Randflake modes, legacy decoding and migration | E |
| Included configuration enhancement | Injected clocks, bounded waiting and immutable epoch normalization | F |
| Included performance work | CUID2/base-codec profiling and justified optimizations, production host benchmarks and soak | G and release gates |

Implement A–C first, then F's time/configuration boundaries, D's runtime binding
and E's explicit formats. Complete G against the resulting common and bound paths.
Run final acceptance after all included changes are present. Keep each cohesive
change reviewable and regression-covered; do not fold unrelated repository cleanup
into this release.

## Review scope and evidence

The review covered all production generator families, configuration objects,
sequence providers, binary/base codecs, value objects, comparator, helpers,
tests, benchmark harnesses, Composer metadata, documentation and CI wrapper.
Graphify supplied navigation; findings below were verified in source and with
targeted PHP probes. Reflection was used only to reach otherwise impractical
counter boundaries, not as evidence that attackers can mutate private state.

Current local evidence on 64-bit PHP 8.5.4:

| Check | Result |
| --- | --- |
| `composer ic:doctor` / `composer ic:list-config` | Doctor healthy; configurations resolve from PHPForge |
| `composer validate --strict` | Passed |
| `composer ic:test:code` | 159 tests, 1,531 assertions, passed; process/fork tests executed |
| `composer ic:tests` | Failed skip-directive scanner and PHPStan configuration validation |
| Other full-suite stages | Normalize, syntax, references, duplicates, comments, Pest, Pint, PHPCS, Deptrac, Psalm and Rector passed |
| `composer audit --locked --format=json` | Zero advisories; abandoned development dependency `doctrine/annotations`; audit exits 1 for abandonment |
| `composer ic:release:guard` with network access | Audit completed with abandonment treated as a warning; guard failed at skip scanning/configuration |
| TypeID upstream vectors | All 9 valid encoding/decoding cases and 21 invalid cases passed |
| Targeted adversarial probes | Reproduced findings R01–R11 below |

The live [Security & Standards run](https://github.com/infocyph/UID/actions/runs/37178593330)
for the reviewed SHA failed all four QA lanes: PHP 8.4/8.5 with prefer-stable and
prefer-lowest. Its analysis lanes, clean install and component benchmark passed.
Job details show failures at `Run quality suite once`; the requested QA log
download returned empty output, so its precise diagnostic is not attributed to
the local failure. Benchmark result validation and regression comparison were
skipped: a green component benchmark job is not a 2% host-RPM certification.

PHP 8.2/8.3 execution, a Windows run, representative host throughput and a long
persistent-worker soak were not performed. The existing documented August
component timings are historical supporting evidence, not current release gates.
Zero published dependency advisories does not establish absence of code defects.
The PHP 8.2/8.3 coverage gap describes the reviewed 5.x tree; the next release's
required runtime matrix begins at PHP 8.4.

## Required findings

Severity describes impact and prerequisites, rather than a claimed CVSS score.

| ID | Priority | Finding and verified evidence | Owner |
| --- | --- | --- | --- |
| R01 | High, conditional local security | `FileLock::acquire()` opens predictable files with `fopen(..., 'c+')` and follows symlinks. A pre-existing `uid-test-1.seq` symlink caused its writable target to become `100,1`. The default shared temporary directory permits precreation attacks by another local user. Cache fallback locks share the opener and can be redirected or obstructed too. This is not a demonstrated remote attack. | `src/Support/FileLock.php:21`, filesystem/cache providers |
| R02 | High, conditional data integrity | PSR-16 state loss restarts allocation at 1 for the same timestamp. Clearing the cache between two calls on the same Randflake config produced identical IDs within one second. Distributed locking alone cannot recover evicted, expired, cleared or lost allocation state. A null TTL may use the backend's default lifetime. | `src/Sequence/PsrSimpleCacheSequenceProvider.php:114`, `src/Randflake.php:256`, provider docs |
| R03 | Medium, ordinary composition | Random ULID generation overwrites `lastRandChars` used by monotonic mode without updating its timestamp. Monotonic → random → monotonic at one timestamp can move backwards. A seeded boundary probe produced `...YYYYYYYYYYYYYYYZ` followed by a smaller random-derived tail. | `src/ULID.php:94` |
| R04 | Medium, extremely rare counter boundary | ULID overflow clears all tail digits before throwing. Calling again at the same explicit timestamp emits `01HF7YAT3V0000000000000001` instead of staying exhausted. This can reuse prior values. | `src/ULID.php:102`, `incrementRandomState()` |
| R05 | Medium, extremely rare counter boundary | UUIDv7 increments an 80-bit tail, then overwrites version and variant bits. Carrying into the variant bits made `018bcfe5-687b-7000-bfff-ffffffffffff` become the smaller `018bcfe5-687b-7000-8000-000000000000`. Version-bit carries have the same underlying problem. | `src/UUID.php:637`, `output()` |
| R06 | Medium, input boundary | ULID, NanoID and TBSL regexes use `$` without strict end-of-input matching and accept one trailing newline. ULID binary conversion silently discards it; TBSL validation and byte decoding disagree. | `src/ULID.php:169`, `src/NanoID.php:40`, `src/TBSL.php:87` |
| R07 | Medium, delayed/retried coordination | Randflake validates a lease before allocation, but resamples time after a provider timestamp exception without checking the lease/lifetime again. A delayed first callback followed by a retry generated an ID with a timestamp later than `leaseEnd`. | `src/Randflake.php:261` |
| R08 | Medium, boundary and operational reliability | Filesystem reservation arithmetic overflows intermediate integer expressions. Starting from `100,9223372036854775806`, size 1 returned the final integer but persisted `100,9.2233720368548E+18`; the next cached allocation raised `TypeError`. | `src/Sequence/FilesystemSequenceProvider.php:78` |
| R09 | Medium, persistent-worker stability | Filesystem `pathCache` is capped at 1,024, but `reservations` retained 1,050 keys after 1,050 domains, even with size 1. Sonyflake retains string-keyed static state after providers disappear; a probe released 250 provider/config domains and retained all 250 entries. `spl_object_id()` reuse can also transfer stale state to an unrelated provider. | `src/Sequence/FilesystemSequenceProvider.php:87`, `src/Sonyflake.php:34`, `generateInternal()` |
| R10 | Medium, public utility contracts | Snowflake/Sonyflake decode eight `ff` bytes to `18446744073709551615`, which their own validators reject. OpaqueId decodes the corresponding full unsigned token `LygHa16AHYF` to `-1`, outside its generation domain. | Numeric decoding in `src/Snowflake.php`, `src/Sonyflake.php`, `src/OpaqueId.php:30` |
| R11 | Medium/low, public utilities | Comparator relations form a cycle: `2 < 10`, `10 < 1a`, `1a < 2`. Sorting mixed numeric/text IDs has no consistent total order. Separately, `UUID::guid(false)` returns literal `\{...\}` on the PHP fallback path, and UUID normalization rejects it. | `src/IdComparator.php:15`, `src/UUID.php:146` |
| R12 | Required release gate | Three `markTestSkipped()` directives fail the strict scanner even though their fork prerequisites exist locally. Installed PHPForge configuration declares `dependency_tree` and `dependency_tree_types`, which installed cognitive-complexity 1.3.0 does not accept. `ic:active-config` also fails. Hosted QA is red. | Tests, PHPForge configuration/dependency pairing, CI |
| R13 | Required portability gate | Composer promises PHP 8.2+, but the reusable workflow currently resolves only 8.4/8.5. Production code also calls `ctype_digit()`/`ctype_xdigit()` without declaring `ext-ctype`. The current host provides ctype, so this is a metadata/support gap, not a reproduced host failure. | `composer.json`, PHPForge runtime matrix |
| R14 | Required documentation accuracy | UUID docs claim a v7 node argument and an `isValid` parse field; neither exists. `UUID::v7(null, $node)` silently ignores the extra positional argument. NanoID docs incorrectly describe customizable rejection sampling instead of its fixed Base64url construction. Sonyflake/Randflake references need to distinguish UID's formats from upstream wire compatibility. | `docs/uuid.rst`, `docs/random-ids.rst`, `docs/compatibility.rst`, references |

### Format and security boundaries

UID's Sonyflake field order is time/machine/sequence. The [upstream implementation](https://raw.githubusercontent.com/sony/sonyflake/master/sonyflake.go)
uses time/sequence/machine. With epoch `1577836800000`, upstream components
elapsed=1, sequence=1, machine=42 encode as `16842794`; UID decodes sequence=42,
machine=256. Current UID docs already describe its 39/16/8 order: preserve that
stored format and explicitly describe it as a UID variant. An upstream-compatible
mode is a separate feature, not a silent parser correction.

UID Randflake uses its own eight-round Feistel permutation. [Upstream Randflake](https://github.com/gosuda/randflake)
uses SPARX64, different byte interpretation and signed decimal presentation.
The [upstream vector file](https://raw.githubusercontent.com/gosuda/randflake/main/test_vectors.json)
defines zero-key token `1qjeojjevu31n` as timestamp=1730000001, node=0,
sequence=0. UID decodes it as timestamp=1999128447, node=39131,
sequence=39141. This proves a compatibility difference, not a cryptanalytic
break. State explicitly that UID's custom permutation has no established
cryptographic security claim; retain the existing warning that inspection does
not authenticate an ID. Do not replace the permutation silently for stored IDs.

The [TypeID 0.3 specification](https://raw.githubusercontent.com/jetify-com/typeid/main/spec/README.md)
allows user-supplied UUID variants while requiring v7 for newly generated IDs.
UID's permissive `fromUuid()` behavior fits that contract and should be preserved.
The [ULID specification](https://github.com/ulid/spec) and
[RFC 9562 section 6.2](https://datatracker.ietf.org/doc/html/rfc9562#section-6.2)
support the monotonicity/overflow acceptance cases for R03–R05.

Positive observations: CSPRNG-backed generation is retained; RandomSampler
uses unbiased rejection sampling; bounded binary/base decoders and malformed
persisted-state rejection are already present; process-random ID families and
filesystem reservations include fork checks; PSR-16 distributed synchronizers
are explicit; loading helpers performs declarations rather than discovery/I/O.
No claim of cryptographic certification is made for a full-library code review.

## Implementation sequence

### A. Filesystem and authoritative allocation safety

- [x] Reproduce R01 using private fixtures, then protect the existing lock/state
  owner against symlinks, non-regular files, unsafe ownership and precreation.
  Use an application-owned restricted directory where possible. Check file
  identity and ownership on the opened handle; a path check alone leaves a race.
  Never open an unverified target with truncating writes.
- [x] Preserve a stable lock inode while coordinating writers. Renaming a state
  file underneath locks can let writers lock different inodes.
- [x] Default secure storage location remains unchanged; if an operator changes location, use the documented coordinated migration
  that preserves sequence high-water marks. Mixed old/new paths or independent
  empty stores must not create two allocation authorities for the same domain.
- [x] Fix R08 with integer-safe bounds checked before increment/reservation,
  including exhaustion, maximum allocation, cached-next and write failures.
- [x] For R02, define shared allocation state as authoritative, non-expiring and
  non-evicting while its timestamp can still be emitted. Require appropriately
  durable storage and cross-host synchronization; generic PSR-16 cannot prove
  those guarantees. Document backend default TTL, clearing, failover, restoration,
  machine ownership and restart requirements. Fail closed when known state is
  lost or allocations regress; a local guard alone is not a distributed fix.
- [x] Add repeated-allocation detection to Randflake's stable provider/domain
  state so ordinary same-instance state loss cannot emit a known duplicate.
  Cover restart/new-provider limitations explicitly. A durable external provider
  can use the existing `SequenceProviderInterface`/callback boundary.
- [x] Preserve the current rule that the fallback cache lock coordinates only
  cooperating processes on one host/filesystem. A Runwire mutex cannot replace
  a distributed lock or an authoritative allocation store.

Acceptance: adversarial link/precreation tests leave target files untouched;
counter exhaustion persists canonical state and yields domain exceptions;
state-loss probes emit no repeated IDs in the supported configuration;
cross-process allocation and migration produce zero duplicate IDs. Test lock
timeout, partial write, corrupt state, process termination and restart. `fflush()`
is not power-loss durability: document filesystem durability limits and use a
durable backend where that guarantee is required.

### B. Generator, validation and utility correctness

- [x] Separate ULID random-mode work from its monotonic state. Make overflow
  state terminal for that timestamp; use a non-mutating overflow decision or
  commit a new tail only after successful increment. Check timestamp range again
  after any wait. Cover alternating modes and repeated calls after exceptions.
- [x] Increment only UUIDv7's usable 74 random bits, carrying across `rand_b`
  and `rand_a` while keeping version/variant fixed. Define full exhaustion and
  explicit timestamp behavior; preserve existing timestamp/output contracts.
- [x] Require exact end-of-input and protocol widths for R06. Align validation,
  parse and byte conversion. Preserve intentionally supported UUID input forms;
  do not turn every normalization helper into an unrelated strictness migration.
- [x] Revalidate Randflake lease, timestamp lifetime and rollback conditions on
  every resampled retry before consuming another allocation. Retain UID's
  documented inclusive lease end in a compatible release.
- [x] Reject decoded numeric IDs outside each family's signed/non-negative
  domain. Test zero, maximum valid value, first invalid value, all-`ff` bytes and
  every supported base, plus value-object construction.
- [x] Establish a total mixed-ID order for R11, for example numeric values first,
  numeric comparison within that group, and lexical comparison within the text
  group. Test transitivity and shuffled input permutations. Numeric-only and
  text-only ordering remain stable. Review changes to previously ambiguous mixed
  ordering against consumers before release; if its pairwise contract must be
  preserved, add explicit modes and schedule the default correction for a major.
- [x] Correct the PHP GUID brace fallback and test normalization/round trips.
- [x] Keep provider-instance state weakly associated with the actual provider;
  replace Sonyflake's reusable object-ID keys. Bound reservation/state metadata
  without resetting live uniqueness or rollback guards. Do not retain empty
  reservation bookkeeping for size 1 without a demonstrated need.
- [x] Define a bounded number of live configured domains for persistent workers.
  Never blindly evict safety state and allow a previously used allocation domain
  to restart. Verify fork, released providers, reused object IDs and request isolation.

Acceptance: each reported defect has a regression that fails on the reviewed
revision and passes after remediation. Boundary tests use controlled state and
time; common-path randomness remains PHP's CSPRNG. Independent golden vectors
verify codecs and protocol envelopes rather than only self-round trips.

### C. Toolchain, support contracts and documentation

- [x] Resolve the cognitive-complexity/PHPForge configuration pairing in its
  owning package. Do not edit `vendor/`, remove the requested checks, add baselines
  or suppress errors. Refresh UID's development resolution once that fix is
  available; verify the same detector and intended rules actually execute.
- [x] Replace skip directives with clear prerequisite assertions for the
  designated process-test environment and supply `pcntl`/process support there.
  Keep meaningful single-process coverage for other platforms. Any suite split
  must be an explicit portability design; the process suite remains a required
  release lane and is never hidden to satisfy the scanner.
- [x] Set Composer runtime requirements to `php: ^8.4` and `php-64bit: ^8.4`
  for the next release. Update installation, requirements and compatibility docs
  together. PHP 8.2/8.3 support ends with the 5.x line; document the upgrade path.
- [x] Verify production source and tooling on real PHP 8.4 and PHP 8.5 in stable
  and lowest-compatible dependency lanes. Test subsequent supported PHP 8.x
  versions as they become available; do not claim PHP 9 compatibility from a
  lower-bound requirement alone. Require platform checks on clean production
  installs and do not use Composer platform emulation as execution evidence.
- [x] Declare mandatory ctype support or remove that dependency with equivalent,
  measured validation. Keep PSR-16 optional and production installs free of tooling.
- [x] Address abandoned dev-package usage through PHPForge/PHPBench ownership;
  the remaining `doctrine/annotations` notice is transitive development tooling,
  Composer audit passes, and no UID production dependency was added to mask it.
- [x] Fix R14 and publish explicit UID-specific Sonyflake/Randflake compatibility
  notes. Include identifier selection, collision budgets for short configurable
  outputs, unique storage constraints and independent authorization requirements.
- [x] Redact Randflake secret-bearing callable parameters with
  `#[SensitiveParameter]`; consider configuration-object exposure separately.
  Attribute redaction does not hide a public property or authorize logging it.

Acceptance: strict full suite and release guard pass on the final source and
dependency set, no new suppression/skip directives, supported-runtime execution
and clean production installation evidence, accurate executable documentation.

## D. Included Runwire 2.1.1 integration

Include an integration focused on coordinated generators and blocking waits,
with representative host measurements as an acceptance gate. Installation and
binding remain optional for consumers. Runwire is not needed for correctness of
unbound generation and provides no clear throughput
advantage for individual UUID, ULID, NanoID, ObjectID, CUID2 or codec CPU operations.

The local Runwire repository's exact `2.1.1` tag was inspected. It requires
PHP 8.4+, matching the next UID release's minimum. `RuntimeContext` exposes
capability/worker metadata, not an injectable distributed allocator or loop
handle. `RequestContext` provides runtime identity, completion, deadline and
cancellation. `CoroutineScope` provides cooperative sleep and local synchronization.

### Proposed instance-based binding

- [x] Use one small operation binding, provisionally `RunwireBinding`, constructed
  from the host's `RuntimeContext`, optional `RequestContext` and optional
  `CoroutineScope`. Let generation configs and coordination providers accept it
  through additive instance APIs. Resolve feature support at binding time.
- [x] Reuse existing config/provider APIs and stable underlying sequence state.
  Do not build a second wrapper hierarchy for every generator or clone allocation
  authority whenever a request binding is created.
- [x] Forward the identical host context/scope references through framework → UID
  and framework → another library → UID. Intermediaries may pass the binding,
  config or provider instance; they must not discover a different global runtime.
- [x] Bind after worker creation/fork. Validate PID, runtime/request identity and
  completed request state; reject stale/cancelled bindings. Do not retain a request
  binding in static provider selectors or worker-wide mutable globals.
- [x] Use public 2.1.1 APIs only. In particular, scope has no public `closed()`
  accessor: its public `hasLocal(TaskLocal)` checks scope openness before querying
  the scheduler. A private library-owned key can validate a passed active scope
  without installing host task-local state. Verify use within the active scheduler
  and cover closed scopes before allocation; do not depend on private internals.
- [x] Use `RequestContext::cancellation` and `CoroutineScope::cancellation()`
  together; cancellation or deadline expiry in either stops new allocation.
  Check after every cooperative suspension and immediately before mutation.
- [x] With an active scope and coroutine support, retry `flock(LOCK_EX | LOCK_NB)`
  with bounded `scope->sleep()` rather than blocking the event loop. Locks are
  not socket readiness: do not register a lock file as an async writable stream.
- [x] Apply the same bounded cooperative strategy to configured clock-rollover
  waits. Use `hrtime()`/Runwire deadlines for wait budgets and wall time for ID
  timestamps and leases. Never derive a Unix ID timestamp from a monotonic clock.
- [x] Re-read/revalidate mutable reservation and sequence state after suspension.
  Keep critical state mutation free of yields. A yielding remote provider needs
  its own serialization/atomicity contract; merely passing a scope cannot supply it.
- [x] Release only UID-owned handles in `finally`. Never start/stop a runtime,
  spawn a worker pool, take over an event loop, complete the host request, close
  the host scope or cancel unrelated host tasks.
- [x] A committed allocation remains consumed if cancellation arrives afterward;
  gaps are acceptable. Do not recycle allocations or retry a possibly committed
  remote write as though it had not happened.
- [x] With no Runwire or no cooperative capability, use the normal synchronous
  path and its configured limits. Missing capability is a fallback condition;
  cancellation, corruption, failed authoritative storage and closed/stale scope
  are terminal errors. Preserve the selected authoritative sequence provider.
- [x] Suggest Runwire to consumers and use it in PHP 8.4+ test fixtures. Keep it
  optional at runtime and test clean supported-PHP installs without Runwire.

Acceptance matrix: direct and intermediary instance forwarding; no Runwire
installed; present but no coroutine scope/capability; active scope with contended
lock; pre-cancelled and expired request/task; cancellation while waiting and
immediately before state mutation; cancellation after allocation commit; mismatched
runtime/PID; completed request; closed scope; fork and worker replacement; no
host-loop blocking; no lifecycle ownership changes; concurrent requests sharing
one authoritative provider without request-state leakage or duplicate IDs.

A host worker slot/generation is useful lifecycle metadata, not a globally unique
Snowflake/Sonyflake node lease. Preserve explicitly coordinated node/machine IDs.
Do not automatically select the process-memory provider for persistent runtimes.

## E. Included upstream-compatible formats and migration

- [x] Add explicit Sonyflake format selection to generation configuration and
  parsing/value APIs. Keep the existing UID time/machine/sequence format readable
  and selectable; add upstream time/sequence/machine behavior as a separate mode.
  Keep numeric storage and epoch units explicit at both generation and parsing.
- [x] Define epoch behavior for each mode and require the same epoch on both
  sides of an interoperability test. Never infer an epoch from an unlabelled ID
  or reuse a custom epoch merely because its integer happens to fit.
- [x] Add explicit Randflake format selection. Preserve decoding and generation
  for the current UID Feistel format and add the upstream SPARX64 format with its
  exact byte order, signed decimal representation and base32hex contract.
  Do not approximate the cipher or substitute a faster custom permutation.
- [x] Pin the upstream reference revision used for each implementation and its
  golden vectors. Validate independent encoding, decoding and generation cases,
  including the upper timestamp range and negative upstream decimal values.
- [x] Make Randflake lease-end semantics explicit per format. Preserve inclusive
  `leaseEnd` for UID legacy mode; use a clearly named exclusive boundary for the
  upstream contract. Document the translation from an inclusive end to an
  exclusive end and validate lifetime/overflow limits during conversion.
- [x] Include format identity in relevant configuration and coordination-domain
  keys where its semantics differ. Share the same authoritative store when
  multiple requests/workers generate within the same configured domain.
- [x] Carry format and epoch metadata in parsed/value representations where needed
  for reliable round trips. Raw stored IDs need an external format discriminator:
  the same bytes can be valid in multiple formats. Do not guess which permutation
  or bit layout produced an unlabelled value.
- [x] Keep legacy format defaults unless the major-release migration explicitly
  changes one. A new upstream mode must not silently reinterpret old stored values.
  Document that changing format does not preserve cross-format uniqueness in one
  unlabelled integer namespace; use appropriate storage keys/constraints.
- [x] Provide executable migration examples: retain old rows with legacy metadata,
  enable explicit dual-format reads, select the desired format for new writes,
  and coordinate writers before changing domains. IDs used as references must not
  be rewritten without a consumer-owned transactional relationship migration.
- [x] Retain clear identifier/authentication boundaries for both modes. Upstream
  compatibility is not authentication or independent cryptographic certification.
  Mark the legacy custom permutation as obfuscation with no established security
  claim; redact secret-bearing parameters for both implementations.
- [x] Keep implementations owned by their existing generator/codec boundaries.
  Add a runtime dependency only if it provides substantial verified value and
  supports the package baseline; a format enhancement does not justify a generic
  cryptography framework or host-specific allocator infrastructure.

Acceptance: independent pinned upstream vectors pass alongside unchanged legacy
vectors; explicit mode and epoch round trips work across numeric, binary and text
representations; wrong/missing metadata fails as documented; dual-format consumer
fixtures retain existing identifiers and relationships. Required allocation,
clock, cancellation and worker tests execute for both modes where applicable.

## F. Included clocks, bounded waits and immutable configuration

- [x] Add instance/configuration injection of PSR-20 `ClockInterface` where
  generation needs a controllable wall clock. Keep explicit timestamp inputs
  usable without another clock abstraction and retain the native fast path when
  no clock is supplied. Keep `psr/clock` optional for consumers that use injection;
  include development fixtures compatible with the PHP 8.4 minimum.
- [x] Preserve public parameter names and add clock/configuration options at real
  existing API boundaries. Never store a request/tenant clock in static globals or
  resolve it repeatedly through a container or runtime singleton.
- [x] Read wall time once per logical allocation attempt, then deliberately
  resample after a retry or rollover wait. Revalidate epoch/lifetime, lease and
  rollback conditions for that sample. Pass the computed value through hot
  internal work rather than allocating a date object per bit/codec operation.
- [x] Keep monotonic timeout accounting separate from injected wall time. A frozen
  test clock must not disable lock/deadline exhaustion or cause an infinite loop.
  Do not substitute Runwire request start time for actual ID generation time.
- [x] Add explicit bounded wait/retry policy for coordinated generators and lock
  acquisition. Cap attempts or elapsed monotonic time, and define a domain failure
  when the budget is exhausted. Combine library limits with the earliest active
  host request/task deadline; retain those limits on synchronous fallback.
- [x] Replace TBSL's tight rollback/rollover spin with bounded waiting. Use the
  passed scope's cooperative sleep where available and a bounded native wait
  otherwise. Measure short normal rollover behavior before selecting intervals.
- [x] Normalize custom epochs at configuration construction into immutable scalar
  milliseconds or immutable date values, and reuse the normalized result.
  Mutating a caller-owned `DateTime` later must not change an existing ID domain.
  Validate supported epoch/range boundaries and preserve parser metadata.
- [x] Document the epoch behavior change in the 6.0 migration: construct a new
  config to change domains, retain the old epoch to parse existing IDs, and avoid
  switching a live generator domain by modifying a shared date object.
- [x] Use deterministic injected-clock tests for lease boundaries, retry resamples,
  rollback, forward jumps, tick rollover, frozen clocks and request cancellation.
  Retain controlled private-state probes only for unreachable counter boundaries.

Acceptance: independent configs/clocks never contaminate one another; mutation of
the original date leaves the configured epoch unchanged; frozen clocks exhaust
their wait budget; cancellation stops before the next allocation mutation; clock
injection and native generation produce equivalent valid timestamps/formats.
Measure clock/date conversion overhead in both isolated and host benchmarks.

## G. Included CUID2 and codec performance work

- [ ] Profile CUID2 generation and fingerprint creation separately, including
  cold initialization, warm calls, configured lengths and fork reseeding. Measure
  SHA3 hashing, Base36 conversion and temporary allocation costs before editing.
- [ ] Profile BaseEncoder, TypeIdCodec and numeric conversions across 8, 10, 12,
  16, 20 and 32 bytes, all supported bases, zero/high-bit/max values, and valid,
  invalid and oversized input. Retain a realistic large-input bound test.
- [ ] Implement measured reductions in repeated conversion, copying, callbacks or
  temporary arrays inside the existing cohesive owners. Consider a direct bit
  codec only for a demonstrated hot compatible base/width; preserve each format's
  padding, alphabet, leading-zero and canonical-input behavior.
- [ ] Reuse common logic only when it remains simpler and improves or preserves
  sustained host RPM. Generic Base32 and TypeID/Crockford alphabets and padding
  are distinct contracts; do not merge them solely because their loops look alike.
- [ ] Preserve CUID2 entropy, digest choice, output distribution/length and fork
  safety. Never replace CSPRNG work or weaken identifier security to win a benchmark.
- [ ] Record before/after component measurements and representative request RPM
  under matching environments. Keep an optimization only when its measured value
  justifies complexity within the release budgets. If no useful candidate wins,
  close the profiling item with the measured decision to retain the current code.

Acceptance: golden vectors and adversarial bounds remain correct, supported modes
produce equivalent valid output, and the selected implementation meets stable host
RPM/resource budgets. Deliver the profiling decision and reproducible measurements;
do not assert an improvement solely from historical microsecond timings.

## Performance and release gates

Previous committed hosted evidence, superseded by the cross-check above:
Security & Standards run `37572646512` on
`6a302ef2c72f9d0e499b4cbc6ff6c97d971819a9` passed clean install, component
benchmarks on PHP 8.4/8.5, analysis on PHP 8.4/8.5, and all four stable/lowest QA
lanes. This is implementation QA evidence, not host-RPM or soak certification.

Release Acceptance run `37572646018` on that revision passed diagnostics and the
persistent-worker soak, but six host lanes exceeded the 2% RPM budget: CUID2 batch
at concurrency 1, 5, 20 and 50, and Snowflake contention at concurrency 1 and 5.
Working-tree remediation adds grouped radix encoding with independent legacy
vectors, omits unused default-provider reservation bookkeeping, reduces repeated
lock ownership lookups, and corrects warm host cache
configuration and median/report handling. All final hosted gates must run again
on the revision containing these changes; earlier soak or QA results do not
certify the modified candidate.

- [ ] Measure corrected code against tag `5.0` with matching runtimes, dependencies,
  hardware and deployment configuration. Separate pure-generator, filesystem,
  reservation, PSR-16 and optional Runwire-bound workloads.
- [ ] Use representative host routes generating one ID and batches of 100 IDs,
  plus contended sequence allocation. Compare at least three warmed sustained
  trials at concurrency 1, 5, 20 and 50, extending the curve if necessary to find
  saturation. Use at least 60 seconds per measured trial after warm-up.
- [ ] Count only valid successful responses; collect successful RPS/RPM, p50/p95/
  p99, errors/timeouts, output/duplicate failures, CPU, peak/steady RSS, worker
  count, lock wait and queue growth. Record extension and OPcache configuration.
- [ ] Use a default maximum 2% median successful-RPM regression on stable
  comparable environments. Record variance; noisy results are inconclusive.
  Bound errors, timeouts and invalid/duplicate IDs at zero in the accepted
  supported workload; queues must not grow progressively. Set workload-specific
  p99, wait and memory ceilings before measurement and explain capacity choices.
- [ ] Run component benchmarks and the existing contention matrix as supporting
  diagnostics. They do not establish host application throughput.
- [ ] Run at least a five-minute persistent-worker soak with repeated requests,
  changing/released configs/providers, cancellations, contention and worker
  replacement. Sample in-load process RSS and lifecycle logs; do not mistake a
  replacement PID for evidence that the old worker retained bounded memory.
- [ ] Establish unbound behavior/performance first. Report Runwire-bound results
  separately and verify useful measured scheduling or throughput behavior within
  the same correctness and resource budgets. Resolve a failing result within
  this scope or report an explicit acceptance blocker; do not silently defer
  the included integration or claim a gain that measurements do not support.
- [ ] Run exact-final-commit hosted QA, stable/lowest dependency lanes, static/
  security checks, supported runtimes, process coordination, documentation checks,
  clean `--no-dev` installation, release guard and relevant host acceptance.
- [ ] Tag only after applicable required gates pass. Keep implementation readiness,
  CI readiness, performance certification and published release/tag status separate.

## Version recommendation and scope

Target **6.0.0** for all sections A–G and the final gates. The expanded plan
includes previously optional delivery work and public behavior corrections.
Keep consumer adoption of Runwire, injected clocks and upstream-compatible
formats explicit. Preserve existing stored-ID decoding and named arguments.
Raise the production minimum to PHP 8.4 as requested; keep Runwire optional
despite the aligned PHP requirement.

- [x] Publish a 5.x → 6.0 migration guide covering mixed-ID ordering, immutable
  epochs, secure sequence-state locations, wait budgets, explicit format/lease
  selection, the PHP 8.4 minimum, optional dependency installation and
  passed-instance composition.
- [x] Inventory public call signatures and defaults against tag `5.0`; verify
  positional and named argument use, helpers, facade calls and value objects.
  Document every intentional major change and keep unrelated contracts stable.
- [x] Add consumer fixtures for old stored IDs, old configs/helpers, direct and
  intermediary Runwire forwarding, and applications without optional packages.
- [ ] Require completion or an explicit measured acceptance decision for every
  included item. A release is blocked while any required implementation,
  compatibility, quality, integration or performance gate remains unresolved.

A **5.0.1** security/correctness hotfix can precede 6.0 if urgent fixes must ship
earlier. It is a scoped subset and does not replace completion of this full plan.
Such a separate 5.x hotfix would preserve that line's existing PHP requirement.
Do not ship the PHP minimum increase or other intentional breaking public
behavior under a 5.x patch/minor version. The requested complete next release
targets **6.0.0 with PHP 8.4+**.
