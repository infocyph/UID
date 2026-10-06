<?php

declare(strict_types=1);

use Infocyph\Runwire\Exception\CancelledException;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\CancellationReason;
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
