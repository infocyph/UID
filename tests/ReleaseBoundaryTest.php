<?php

declare(strict_types=1);

use Infocyph\Runwire\Exception\CancelledException;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\CancellationReason;
use Infocyph\Runwire\RuntimeContext;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Enums\SonyflakeFormat;
use Infocyph\UID\Exceptions\SonyflakeException;
use Infocyph\UID\Exceptions\UIDException;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Runtime\RunwireBinding;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Sequence\InMemorySequenceProvider;
use Infocyph\UID\Sonyflake;
use Infocyph\UID\TBSL;
use Psr\Clock\ClockInterface;

final readonly class ReleaseBoundaryClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $time) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}

test('bound filesystem providers reject cancellation before fresh and reserved allocation', function (int $reservationSize): void {
    $directory = sys_get_temp_dir() . '/uid-boundary-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $host = RuntimeContext::standalone();
    $request = RequestContext::create($host);
    $provider = new FilesystemSequenceProvider(
        $directory,
        reservationSize: $reservationSize,
        runtime: new GenerationContext(runwire: new RunwireBinding($host, $request)),
    );
    $path = $directory . '/uid-test-1.seq';

    try {
        expect($provider->next('test', 1, 100))->toBe(1);
        $before = file_get_contents($path);
        $request->cancel(CancellationReason::HOST_CANCELLED);
        expect(fn(): int => $provider->next('test', 1, 100))->toThrow(CancelledException::class)
            ->and(file_get_contents($path))->toBe($before);
    } finally {
        unlink($path);
        rmdir($directory);
    }
})->with([1, 8]);

test('TBSL clocks remain independent across provider and machine domains', function (): void {
    $lateClock = new GenerationContext(clock: new ReleaseBoundaryClock(new DateTimeImmutable('@1800000000')));
    $earlyClock = new GenerationContext(clock: new ReleaseBoundaryClock(new DateTimeImmutable('@1700000000')));
    $provider = new InMemorySequenceProvider();
    TBSL::generateWithConfig(new TBSLConfig(sequenceProvider: $provider, runtime: $lateClock));

    foreach ([
        new TBSLConfig(sequenceProvider: new InMemorySequenceProvider(), runtime: $earlyClock, clockBackwardPolicy: ClockBackwardPolicy::THROW),
        new TBSLConfig(machineId: 1, sequenceProvider: $provider, runtime: $earlyClock, clockBackwardPolicy: ClockBackwardPolicy::THROW),
    ] as $config) {
        $id = TBSL::generateWithConfig($config);
        expect(TBSL::parse($id)['time']->getTimestamp())->toBe(1_700_000_000);
    }

    expect(fn(): string => TBSL::generateWithConfig(new TBSLConfig(
        sequenceProvider: $provider,
        runtime: $earlyClock,
        clockBackwardPolicy: ClockBackwardPolicy::THROW,
    )))->toThrow(UIDException::class, 'Clock moved backwards');
});

test('Sonyflake rejects future epochs inside one clock tick', function (SonyflakeFormat $format, int $offset): void {
    $provider = new InMemorySequenceProvider();
    $config = new SonyflakeConfig(
        customEpoch: 1_700_000_000_000 + $offset,
        sequenceProvider: $provider,
        runtime: new GenerationContext(clock: new ReleaseBoundaryClock(new DateTimeImmutable('@1700000000'))),
        format: $format,
    );

    expect(fn(): string => Sonyflake::generateWithConfig($config))
        ->toThrow(SonyflakeException::class, 'epoch must not be in the future');
})->with([SonyflakeFormat::UID, SonyflakeFormat::UPSTREAM])->with([1, 9]);

test('TBSL preserves eleven-digit seconds within its 60-bit timestamp field', function (): void {
    $runtime = new GenerationContext(clock: new ReleaseBoundaryClock(new DateTimeImmutable('@10000000000.123456')));
    $id = TBSL::generateWithConfig(new TBSLConfig(machineId: 99, sequenceProvider: new InMemorySequenceProvider(), runtime: $runtime));
    $parsed = TBSL::parse($id);
    expect($parsed['time']->format('U.u'))->toBe('10000000000.123456')
        ->and($parsed['machineId'])->toBe(99);
});

test('generation rejects out-of-domain clocks and keeps large wait budgets integer safe', function (): void {
    $negative = new GenerationContext(clock: new ReleaseBoundaryClock(new DateTimeImmutable('1969-12-31T23:59:59.500000Z')));
    expect(fn(): int => $negative->nowMicroseconds())->toThrow(InvalidArgumentException::class)
        ->and((new GenerationContext(waitTimeoutMicros: PHP_INT_MAX))->waitDeadlineNanoseconds())->toBeInt();
});

test('upstream Randflake lease conversion fails with a domain error at integer exhaustion', function (): void {
    $config = new \Infocyph\UID\Configuration\RandflakeConfig(
        0, 1_730_000_000, PHP_INT_MAX, '0123456789abcdef',
        format: \Infocyph\UID\Enums\RandflakeFormat::UPSTREAM,
    );
    expect(fn(): string => \Infocyph\UID\Randflake::generateWithConfig($config))
        ->toThrow(\Infocyph\UID\Exceptions\RandflakeException::class, 'exclusive boundary');
});

test('Randflake rechecks live domain state after reentrant provider work', function (\Infocyph\UID\Enums\RandflakeFormat $format): void {
    $nested = false;
    $config = null;
    $nestedId = null;
    $provider = new \Infocyph\UID\Sequence\CallbackSequenceProvider(
        function (string $type, int $machineId, int $timestamp) use (&$nested, &$config, &$nestedId): int {
            unset($type, $machineId, $timestamp);
            if (!$nested) {
                $nested = true;
                $nestedId = \Infocyph\UID\Randflake::generateWithConfig($config);
            }

            return 1;
        },
    );
    $config = new \Infocyph\UID\Configuration\RandflakeConfig(
        0, 1_730_000_000, 1_730_000_005, '0123456789abcdef',
        sequenceProvider: $provider,
        runtime: new GenerationContext(clock: new ReleaseBoundaryClock(new DateTimeImmutable('@1730000001'))),
        format: $format,
    );

    expect(fn(): string => \Infocyph\UID\Randflake::generateWithConfig($config))
        ->toThrow(\Infocyph\UID\Exceptions\RandflakeException::class, 'allocation regressed')
        ->and($nestedId)->toBeString();
})->with([\Infocyph\UID\Enums\RandflakeFormat::UID, \Infocyph\UID\Enums\RandflakeFormat::UPSTREAM]);

test('UUID node arguments reject trailing line breaks', function (): void {
    foreach (['v1', 'v6', 'v8'] as $method) {
        expect(fn(): string => \Infocyph\UID\UUID::$method("0123456789ab\n"))
            ->toThrow(\Infocyph\UID\Exceptions\UUIDException::class);
    }
});

test('implicit ULID and UUID generation fail promptly at terminal timestamp exhaustion', function (): void {
    foreach ([
        [\Infocyph\UID\ULID::class, 'waitForNextMillisecond', \Infocyph\UID\Exceptions\ULIDException::class],
        [\Infocyph\UID\UUID::class, 'nextV7Timestamp', \Infocyph\UID\Exceptions\UUIDException::class],
    ] as [$class, $method, $exception]) {
        $wait = new ReflectionMethod($class, $method);
        expect(fn(): int => $wait->invoke(null, 281_474_976_710_655))->toThrow($exception, 'exhausted');
    }
});

test('Randflake rejects injected times before its epoch without consuming an allocation', function (): void {
    $calls = 0;
    $provider = new \Infocyph\UID\Sequence\CallbackSequenceProvider(function (string $type, int $machineId, int $timestamp) use (&$calls): int {
        unset($type, $machineId, $timestamp);
        return ++$calls;
    });
    $config = new \Infocyph\UID\Configuration\RandflakeConfig(
        0, 0, 1_730_000_005, '0123456789abcdef',
        sequenceProvider: $provider,
        runtime: new GenerationContext(clock: new ReleaseBoundaryClock(new DateTimeImmutable('@1729999999'))),
    );
    expect(fn(): string => \Infocyph\UID\Randflake::generateWithConfig($config))
        ->toThrow(\Infocyph\UID\Exceptions\RandflakeException::class)
        ->and($calls)->toBe(0);
});

test('Sonyflake rejects extreme epochs with a domain error before integer division', function (): void {
    $config = new SonyflakeConfig(
        customEpoch: PHP_INT_MIN,
        sequenceProvider: new InMemorySequenceProvider(),
        runtime: new GenerationContext(clock: new ReleaseBoundaryClock(new DateTimeImmutable('@1700000000'))),
    );
    expect(fn(): string => Sonyflake::generateWithConfig($config))
        ->toThrow(SonyflakeException::class, 'maximum life cycle');
});
