<?php

declare(strict_types=1);

use Infocyph\Runwire\Coroutine\CoroutineRuntime;
use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\Exception\CancelledException;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\RuntimeCapabilities;
use Infocyph\Runwire\Runtime\Enum\CancellationReason;
use Infocyph\Runwire\Runtime\Enum\RuntimeDriver;
use Infocyph\Runwire\RuntimeContext;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Exceptions\SnowflakeException;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Runtime\RunwireBinding;
use Infocyph\UID\Sequence\InMemorySequenceProvider;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Infocyph\UID\Snowflake;
use Psr\Clock\ClockInterface;

final readonly class FrozenUidClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $time) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}

final class OverflowUidSequenceProvider implements SequenceProviderInterface
{
    public function next(string $type, int $machineId, int $timestamp): int
    {
        unset($type, $machineId, $timestamp);

        return 4_097;
    }
}

test('frozen injected clocks exhaust the bounded generation wait', function (): void {
    $runtime = new GenerationContext(
        clock: new FrozenUidClock(new DateTimeImmutable('2026-10-06T15:00:00.000000+00:00')),
        waitTimeoutMicros: 2_000,
    );
    $config = new SnowflakeConfig(
        sequenceProvider: new OverflowUidSequenceProvider(),
        runtime: $runtime,
    );

    expect(fn(): string => Snowflake::generateWithConfig($config))
        ->toThrow(SnowflakeException::class, 'Timed out waiting for a valid Snowflake timestamp');
});

test('Runwire cancellation stops generation before allocation', function (): void {
    $host = RuntimeContext::standalone();
    $request = RequestContext::create($host);
    $binding = new RunwireBinding($host, $request);
    $runtime = new GenerationContext(runwire: $binding);
    $provider = new InMemorySequenceProvider();

    $request->cancel(CancellationReason::HOST_CANCELLED);

    expect(fn(): string => Snowflake::generateWithConfig(new SnowflakeConfig(
        sequenceProvider: $provider,
        runtime: $runtime,
    )))->toThrow(CancelledException::class)
        ->and($provider->next('probe', 0, 1))->toBe(1);
});

test('native generation remains available without optional runtime binding', function (): void {
    expect(Snowflake::isValid(Snowflake::generateWithConfig(new SnowflakeConfig(
        sequenceProvider: new InMemorySequenceProvider(),
    ))))->toBeTrue();
});


test('Runwire binding rejects a request from another runtime', function (): void {
    $left = RuntimeContext::standalone();
    $right = RuntimeContext::standalone();
    $request = RequestContext::create($left);

    expect(fn(): RunwireBinding => new RunwireBinding($right, $request))
        ->toThrow(LogicException::class, 'different runtime context');
});

test('Runwire binding rejects completed requests', function (): void {
    $host = RuntimeContext::standalone();
    $request = RequestContext::create($host);
    $request->complete();

    expect(fn(): RunwireBinding => new RunwireBinding($host, $request))
        ->toThrow(LogicException::class, 'already completed');
});

test('Runwire binding preserves exact host instances and cooperative scope waits', function (): void {
    $capabilities = new RuntimeCapabilities(
        driver: RuntimeDriver::NATIVE,
        runwireLoopAvailable: true,
        supportsRunwireCoroutines: true,
    );
    $host = RuntimeContext::fromCapabilities($capabilities, 'uid-test', concurrent: true);
    $request = RequestContext::create($host);
    $coroutines = new CoroutineRuntime();

    $result = $coroutines->runRequest(
        $request,
        function (CoroutineScope $scope) use ($host, $request): array {
            $binding = new RunwireBinding($host, $request, $scope);
            $runtime = new GenerationContext(runwire: $binding, waitTimeoutMicros: 10_000);
            $runtime->sleepMicroseconds(100);

            return [
                $binding->runtime === $host,
                $binding->request === $request,
                $binding->scope === $scope,
            ];
        },
    );

    expect($result)->toBe([true, true, true])
        ->and($request->completed())->toBeFalse();
});

test('a cancellation after allocation never recycles the consumed allocation', function (): void {
    $host = RuntimeContext::standalone();
    $request = RequestContext::create($host);
    $runtime = new GenerationContext(runwire: new RunwireBinding($host, $request));
    $allocations = 0;
    $provider = new \Infocyph\UID\Sequence\CallbackSequenceProvider(
        static function (string $type, int $machineId, int $timestamp) use (&$allocations, $request): int {
            unset($type, $machineId, $timestamp);
            ++$allocations;
            $request->cancel(CancellationReason::HOST_CANCELLED);

            return $allocations;
        },
    );

    Snowflake::generateWithConfig(new SnowflakeConfig(
        sequenceProvider: $provider,
        runtime: $runtime,
    ));

    expect($allocations)->toBe(1)
        ->and(fn(): string => Snowflake::generateWithConfig(new SnowflakeConfig(
            sequenceProvider: $provider,
            runtime: $runtime,
        )))->toThrow(CancelledException::class)
        ->and($allocations)->toBe(1);
});
