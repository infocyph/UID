<?php

declare(strict_types=1);

use Infocyph\UID\Configuration\RandflakeConfig;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Exceptions\RandflakeException;
use Infocyph\UID\Exceptions\SnowflakeException;
use Infocyph\UID\Exceptions\SonyflakeException;
use Infocyph\UID\Randflake;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Sequence\CallbackSequenceProvider;
use Infocyph\UID\Snowflake;
use Infocyph\UID\Sonyflake;
use Psr\Clock\ClockInterface;

final readonly class PersistentDomainClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@1800000000.123456');
    }
}

function statelessDomainProvider(): CallbackSequenceProvider
{
    return new CallbackSequenceProvider(
        static fn(string $type, int $machineId, int $timestamp): int => 1,
    );
}

test('Snowflake safety state fails closed instead of evicting live domains', function (): void {
    $provider = statelessDomainProvider();
    $runtime = new GenerationContext(clock: new PersistentDomainClock());

    for ($domain = 0; $domain < 1024; ++$domain) {
        Snowflake::generateWithConfig(new SnowflakeConfig(
            customEpoch: 1_700_000_000_000 + $domain,
            sequenceProvider: $provider,
            runtime: $runtime,
        ));
    }

    expect(fn(): string => Snowflake::generateWithConfig(new SnowflakeConfig(
        customEpoch: 1_700_000_002_000,
        sequenceProvider: $provider,
        runtime: $runtime,
    )))->toThrow(SnowflakeException::class, 'domain limit exceeded');
});

test('Sonyflake safety state fails closed instead of evicting live domains', function (): void {
    $provider = statelessDomainProvider();
    $runtime = new GenerationContext(clock: new PersistentDomainClock());

    for ($machineId = 0; $machineId < 1024; ++$machineId) {
        Sonyflake::generateWithConfig(new SonyflakeConfig(
            machineId: $machineId,
            sequenceProvider: $provider,
            runtime: $runtime,
        ));
    }

    expect(fn(): string => Sonyflake::generateWithConfig(new SonyflakeConfig(
        machineId: 1024,
        sequenceProvider: $provider,
        runtime: $runtime,
    )))->toThrow(SonyflakeException::class, 'domain limit exceeded');
});

test('Randflake safety state fails closed instead of evicting live domains', function (): void {
    $provider = statelessDomainProvider();
    $runtime = new GenerationContext(clock: new PersistentDomainClock());
    $secret = '0123456789abcdef';

    for ($nodeId = 0; $nodeId < 1024; ++$nodeId) {
        Randflake::generateWithConfig(new RandflakeConfig(
            nodeId: $nodeId,
            leaseStart: 1_799_999_999,
            leaseEnd: 1_800_000_001,
            secret: $secret,
            sequenceProvider: $provider,
            runtime: $runtime,
        ));
    }

    expect(fn(): string => Randflake::generateWithConfig(new RandflakeConfig(
        nodeId: 1024,
        leaseStart: 1_799_999_999,
        leaseEnd: 1_800_000_001,
        secret: $secret,
        sequenceProvider: $provider,
        runtime: $runtime,
    )))->toThrow(RandflakeException::class, 'domain limit exceeded');
});
