Framework Integration
=====================

Laravel
-------

- Helper functions are available through Composer autoload.
- Prefer UUIDv7 or ULID for ordered primary keys.
- Keep IDs as strings at application boundaries; convert to binary only at persistence edges.

Symfony
-------

- Helper functions are available through Composer autoload.
- Prefer central generation through Infocyph\\UID\\Id inside services.

Generic PHP Apps
----------------

Use config objects for coordinated generators and keep them scoped to the
application domain that owns their node/machine ID, epoch, sequence provider and
runtime policy. SnowflakeConfig, SonyflakeConfig, RandflakeConfig and TBSLConfig
may receive a GenerationContext without changing the ordinary synchronous APIs.

Runwire 2.1 Integration
-----------------------

Runwire support is optional. UID never discovers a runtime globally and never
starts, stops, drives or closes a Runwire runtime, request, scope or event loop.
The host passes the exact runtime/request/scope instances that already own the
operation.

.. code-block:: php

   <?php

   use Infocyph\Runwire\Coroutine\CoroutineScope;
   use Infocyph\Runwire\RequestContext;
   use Infocyph\Runwire\RuntimeContext;
   use Infocyph\UID\Configuration\SnowflakeConfig;
   use Infocyph\UID\Runtime\GenerationContext;
   use Infocyph\UID\Runtime\RunwireBinding;
   use Infocyph\UID\Snowflake;

   function issueId(
       RuntimeContext $runtime,
       RequestContext $request,
       CoroutineScope $scope,
   ): string {
       $binding = new RunwireBinding($runtime, $request, $scope);
       $generation = new GenerationContext(runwire: $binding);

       return Snowflake::generateWithConfig(
           new SnowflakeConfig(runtime: $generation),
       );
   }

Construct bindings after worker creation/fork and do not retain a request binding
in static provider selectors or worker-wide mutable state. The binding validates
the process, request/runtime identity, request completion, active coroutine
capability, cancellation and deadlines using Runwire 2.1 public APIs.

A CoroutineScope does not expose public request identity. Therefore the host
boundary that owns both objects is responsible for pairing the correct scope with
the request. UID verifies that the scope is active and that the request belongs to
the supplied runtime; it does not depend on Runwire private internals.

When a scope is supplied, UID uses cooperative sleeps for lock and rollover waits.
The generator forwards its GenerationContext to built-in filesystem and PSR-16
providers for that allocation. A shared provider does not retain the operation's
request binding; subsequent requests can supply their own context through configs.
Custom providers remain responsible for their own synchronization and cooperative
I/O. A context supplied when constructing a provider remains a scoped default;
do not keep a request-bound provider after that request completes.
Without Runwire, the same bounded policies use native synchronous waits. Missing
Runwire is a normal fallback condition; cancellation, an expired deadline, stale
process identity, completed requests, corrupt state and authoritative-store
failures remain terminal.

PSR-20 Clock Injection
----------------------

GenerationContext optionally accepts Psr\\Clock\\ClockInterface for a
controllable wall clock. Monotonic wait accounting remains based on hrtime() and
Runwire deadlines, so a frozen test clock cannot create an infinite lock or
rollover wait. Explicit timestamp APIs continue to work without a clock object.
