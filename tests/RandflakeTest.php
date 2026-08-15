<?php

declare(strict_types=1);

use Infocyph\UID\Configuration\RandflakeConfig;
use Infocyph\UID\Randflake;

use function Infocyph\UID\randflake;

test('Randflake basic generation and parsing', function () {
    $now = time();
    $leaseStart = $now - 5;
    $leaseEnd = $now + 300;
    $secret = 'super-secret-key';

    $id = Randflake::generate(42, $leaseStart, $leaseEnd, $secret);
    $inspect = Randflake::inspect($id, $secret);
    $parsed = Randflake::parse($id, $secret);

    expect(Randflake::isValid($id))->toBeTrue()
        ->and($inspect['node_id'])->toBe(42)
        ->and($parsed['node_id'])->toBe(42)
        ->and($parsed['sequence'])->toBeGreaterThanOrEqual(0)
        ->and($parsed['sequence'])->toBeLessThanOrEqual(Randflake::MAX_SEQUENCE)
        ->and($parsed['time']->getTimestamp())->toBeBetween($leaseStart, $leaseEnd);
});

test('Randflake uniqueness on repeated generation', function () {
    $now = time();
    $leaseStart = $now - 5;
    $leaseEnd = $now + 300;
    $secret = 'super-secret-key';

    $ids = [];
    for ($index = 0; $index < 200; $index++) {
        $ids[] = Randflake::generate(7, $leaseStart, $leaseEnd, $secret);
    }

    expect(count(array_unique($ids)))->toBe(count($ids));
});

test('Randflake string encode and parse roundtrip', function () {
    $now = time();
    $leaseStart = $now - 5;
    $leaseEnd = $now + 300;
    $secret = 'super-secret-key';

    $stringId = Randflake::generateString(9, $leaseStart, $leaseEnd, $secret);
    $decoded = Randflake::decodeString($stringId);
    $parsed = Randflake::parseString($stringId, $secret);

    expect($stringId)->toMatch('/^[0-9a-v]+$/')
        ->and($decoded)->toMatch('/^\d+$/')
        ->and($parsed['node_id'])->toBe(9)
        ->and($parsed['time']->getTimestamp())->toBeBetween($leaseStart, $leaseEnd);
});

test('Randflake bytes and base conversion roundtrip', function () {
    $now = time();
    $leaseStart = $now - 5;
    $leaseEnd = $now + 300;
    $secret = 'super-secret-key';

    $id = Randflake::generate(3, $leaseStart, $leaseEnd, $secret);
    $bytes = Randflake::toBytes($id);
    $encoded = Randflake::toBase($id, 58);

    expect(strlen($bytes))->toBe(8)
        ->and(Randflake::fromBytes($bytes))->toBe($id)
        ->and(Randflake::fromBase($encoded, 58))->toBe($id);
});

test('Randflake validates secret node and lease', function () {
    $now = time();
    $validSecret = 'super-secret-key';

    expect(fn () => Randflake::generate(-1, $now - 1, $now + 10, $validSecret))
        ->toThrow(\Infocyph\UID\Exceptions\RandflakeException::class)
        ->and(fn () => Randflake::generate(1, $now - 1, $now + 10, 'too-short'))
        ->toThrow(\Infocyph\UID\Exceptions\RandflakeException::class)
        ->and(fn () => Randflake::generate(1, $now + 10, $now + 20, $validSecret))
        ->toThrow(\Infocyph\UID\Exceptions\RandflakeException::class);
});

test('Randflake config returns its canonical decimal representation', function () {
    $now = time();
    $leaseStart = $now - 5;
    $leaseEnd = $now + 300;
    $secret = 'super-secret-key';

    $stringId = Randflake::generateWithConfig(
        new RandflakeConfig(
            nodeId: 2,
            leaseStart: $leaseStart,
            leaseEnd: $leaseEnd,
            secret: $secret,
        ),
    );

    expect($stringId)->toBeString()->toMatch('/^\d+$/');
});

test('Randflake namespaced helper delegates to the generator', function () {
    $now = time();
    $leaseStart = $now - 5;
    $leaseEnd = $now + 300;
    $secret = 'super-secret-key';

    $id = randflake(new RandflakeConfig(4, $leaseStart, $leaseEnd, $secret));

    expect(Randflake::isValid($id))->toBeTrue()
        ->and(Randflake::parse($id, $secret)['node_id'])->toBe(4);
});
