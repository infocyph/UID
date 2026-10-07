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


function forwardUidSnowflake(SnowflakeConfig $config): string
{
    return Snowflake::generateWithConfig($config);
}

test('Runwire bindings survive intermediary config forwarding without rediscovery', function (): void {
    $host = RuntimeContext::standalone();
    $request = RequestContext::create($host);
    $binding = new RunwireBinding($host, $request);
    $config = new SnowflakeConfig(
        sequenceProvider: new InMemorySequenceProvider(),
        runtime: new GenerationContext(runwire: $binding),
    );

    expect(forwardUidSnowflake($config))->toBeString()
        ->and($config->runtime?->runwire)->toBe($binding)
        ->and($binding->runtime)->toBe($host)
        ->and($binding->request)->toBe($request);
});

test('Runwire bindings reject scopes after the host closes them', function (): void {
    $capabilities = new RuntimeCapabilities(
        driver: RuntimeDriver::NATIVE,
        runwireLoopAvailable: true,
        supportsRunwireCoroutines: true,
    );
    $host = RuntimeContext::fromCapabilities($capabilities, 'uid-closed-scope', concurrent: true);
    $request = RequestContext::create($host);
    $coroutines = new CoroutineRuntime();
    $capturedScope = null;

    $coroutines->runRequest(
        $request,
        function (CoroutineScope $scope) use (&$capturedScope): void {
            $capturedScope = $scope;
        },
    );

    expect($capturedScope)->toBeInstanceOf(CoroutineScope::class)
        ->and(fn(): RunwireBinding => new RunwireBinding($host, $request, $capturedScope))
        ->toThrow(LogicException::class, 'already closed');
});

test('contended locks suspend cooperatively and stop on host cancellation', function (bool $cancel): void {
    $directory = sys_get_temp_dir() . '/uid-cooperative-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $path = $directory . '/uid-snowflake-0.seq';
    file_put_contents($path, '1700000000000,7');
    $held = fopen($path, 'r+b');
    expect(flock($held, LOCK_EX))->toBeTrue();
    $host = RuntimeContext::fromCapabilities(new RuntimeCapabilities(
        driver: RuntimeDriver::NATIVE,
        runwireLoopAvailable: true,
        supportsRunwireCoroutines: true,
    ), 'uid-contended', concurrent: true);
    $request = RequestContext::create($host);
    $coroutines = new CoroutineRuntime();
    $ranOtherTask = false;

    $allocate = function () use ($coroutines, $request, $host, $held, $directory, $cancel, &$ranOtherTask): string {
        return $coroutines->runRequest($request, function (CoroutineScope $scope) use ($request, $host, $held, $directory, $cancel, &$ranOtherTask): string {
            $scope->spawn(function () use ($scope, $request, $held, $cancel, &$ranOtherTask): void {
                $scope->sleep(0.005);
                $ranOtherTask = true;
                if ($cancel) {
                    $request->cancel(CancellationReason::HOST_CANCELLED);
                }
                flock($held, LOCK_UN);
            });
            // A worker-owned provider receives each request's binding through the config.
            $provider = new \Infocyph\UID\Sequence\FilesystemSequenceProvider($directory);

            return forwardUidSnowflake(new SnowflakeConfig(
                sequenceProvider: $provider,
                runtime: new GenerationContext(
                    clock: new FrozenUidClock(new DateTimeImmutable('@1700000000')),
                    runwire: new RunwireBinding($host, $request, $scope),
                    waitTimeoutMicros: 100_000,
                ),
            ));
        });
    };

    try {
        if ($cancel) {
            expect($allocate)->toThrow(CancelledException::class)
                ->and(file_get_contents($path))->toBe('1700000000000,7');
        } else {
            expect(Snowflake::parse($allocate())['sequence'])->toBe(7)
                ->and(file_get_contents($path))->toBe('1700000000000,8')
                ->and($request->completed())->toBeFalse();
        }
        expect($ranOtherTask)->toBeTrue();
    } finally {
        flock($held, LOCK_UN);
        fclose($held);
        unlink($path);
        rmdir($directory);
    }
})->with([false, true]);

test('lock timeout forbids allocation after a late cooperative wake', function (): void {
    $directory = sys_get_temp_dir() . '/uid-late-wake-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $path = $directory . '/uid-test-1.seq';
    file_put_contents($path, '100,1');
    $held = fopen($path, 'r+b');
    expect(flock($held, LOCK_EX))->toBeTrue();
    $host = RuntimeContext::fromCapabilities(new RuntimeCapabilities(
        driver: RuntimeDriver::NATIVE,
        runwireLoopAvailable: true,
        supportsRunwireCoroutines: true,
    ), 'uid-late-wake', concurrent: true);
    $request = RequestContext::create($host);
    $coroutines = new CoroutineRuntime();

    try {
        $failure = $coroutines->runRequest($request, function (CoroutineScope $scope) use ($host, $request, $held, $directory): ?\Infocyph\UID\Exceptions\FileLockException {
            $scope->spawn(function () use ($held): void {
                // Simulate host work delaying the allocator's scheduled wake.
                usleep(10_000);
                flock($held, LOCK_UN);
            });
            $provider = new \Infocyph\UID\Sequence\FilesystemSequenceProvider($directory);
            try {
                $provider->next('test', 1, 100, new GenerationContext(
                    runwire: new RunwireBinding($host, $request, $scope),
                    waitTimeoutMicros: 5_000,
                ));
            } catch (\Infocyph\UID\Exceptions\FileLockException $exception) {
                return $exception;
            }

            return null;
        });
        expect($failure)->toBeInstanceOf(\Infocyph\UID\Exceptions\FileLockException::class)
            ->and(file_get_contents($path))->toBe('100,1')
            ->and($request->completed())->toBeFalse();
    } finally {
        flock($held, LOCK_UN);
        fclose($held);
        unlink($path);
        rmdir($directory);
    }
});
