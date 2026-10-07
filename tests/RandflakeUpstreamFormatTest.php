<?php

declare(strict_types=1);

use Infocyph\UID\Configuration\RandflakeConfig;
use Infocyph\UID\Enums\RandflakeFormat;
use Infocyph\UID\Randflake;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Sequence\InMemorySequenceProvider;
use Psr\Clock\ClockInterface;

final readonly class RandflakeVectorClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@1730000001');
    }
}

test('Randflake upstream mode matches the pinned zero-key vector', function (): void {
    $secret = str_repeat("\0", 16);
    $config = new RandflakeConfig(
        nodeId: 0,
        leaseStart: 1_730_000_000,
        leaseEnd: 1_730_000_009,
        secret: $secret,
        sequenceProvider: new InMemorySequenceProvider(),
        runtime: new GenerationContext(clock: new RandflakeVectorClock()),
        format: RandflakeFormat::UPSTREAM,
        leaseEndExclusive: 1_730_000_010,
    );

    $id = Randflake::generateWithConfig($config);
    expect($id)->toBe('2111581968557607991')
        ->and(Randflake::encodeString($id, RandflakeFormat::UPSTREAM))->toBe('1qjeojjevu31n')
        ->and(Randflake::decodeString('1qjeojjevu31n', RandflakeFormat::UPSTREAM))->toBe($id)
        ->and(Randflake::inspect($id, $secret, RandflakeFormat::UPSTREAM))->toBe([
            'timestamp' => 1_730_000_001,
            'node_id' => 0,
            'sequence' => 0,
        ]);
});

test('Randflake upstream codec preserves signed vectors', function (): void {
    expect(Randflake::decodeString('fphhelk04q8f8', RandflakeFormat::UPSTREAM))
        ->toBe('-232447010193727000')
        ->and(Randflake::encodeString('-232447010193727000', RandflakeFormat::UPSTREAM))
        ->toBe('fphhelk04q8f8');
});

test('Randflake legacy format remains the default', function (): void {
    $secret = '0123456789abcdef';
    $id = Randflake::generate(
        nodeId: 7,
        leaseStart: time() - 1,
        leaseEnd: time() + 5,
        secret: $secret,
    );

    expect(Randflake::isValid($id))->toBeTrue()
        ->and(Randflake::encodeString(Randflake::decodeString(Randflake::encodeString($id))))->toBe(
            Randflake::encodeString($id),
        );
});
