<?php

use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Sonyflake;

beforeEach(function () {
    Sonyflake::useInMemorySequenceProvider();
});

afterEach(function () {
    Sonyflake::resetSequenceProvider();
});

test('Sonyflake Basic Functionality', function () {
    $startedAt = time() - 1;
    $sf = Sonyflake::generate();
    $finishedAt = time() + 1;
    $parsed = Sonyflake::parse($sf);

    expect($parsed['time']->getTimestamp())->toBeBetween($startedAt, $finishedAt)
        ->and($parsed['machine_id'])->toBe(0);
});

test('Sonyflake ID Uniqueness', function () {
    $ids = [];
    for ($i = 0; $i < 100; $i++) {
        $ids[] = Sonyflake::generate();
    }

    expect(count(array_unique($ids)))->toBe(count($ids));
});

test('Sonyflake Sequential Order', function () {
    $previous = (int) Sonyflake::generate();
    for ($i = 0; $i < 100; $i++) {
        $current = (int) Sonyflake::generate();
        expect($current)->toBeGreaterThan($previous);
        $previous = $current;
    }
});

test('Sonyflake Machine ID Differentiation', function () {
    $id1 = Sonyflake::generate(1);
    $id2 = Sonyflake::generate(2);

    $parsed1 = Sonyflake::parse($id1);
    $parsed2 = Sonyflake::parse($id2);

    expect($parsed1['machine_id'])->not->toBe($parsed2['machine_id']);
});

test('Sonyflake Max Sequence Handling', function () {
    $firstTimestamp = null;
    $attempts = 0;

    Sonyflake::useSequenceCallback(function (string $type, int $machineId, int $timestamp) use (
        &$firstTimestamp,
        &$attempts
    ): int {
        unset($type, $machineId);
        $attempts++;

        if ($firstTimestamp === null) {
            $firstTimestamp = $timestamp;
            return 256;
        }

        if ($timestamp === $firstTimestamp) {
            if ($attempts > 64) {
                throw new \RuntimeException('Sonyflake did not advance timestamp after sequence overflow');
            }

            return 256;
        }

        return 1;
    });

    $id = Sonyflake::generate();
    $parsed = Sonyflake::parse($id);

    expect($parsed['sequence'])->toBe(1)
        ->and($attempts)->toBeGreaterThan(1);
});

test('Sonyflake validation helper', function () {
    $id = Sonyflake::generate();

    expect(Sonyflake::isValid($id))->toBeTrue()
        ->and(Sonyflake::isValid('abc'))->toBeFalse()
        ->and(Sonyflake::isValid('0'))->toBeFalse();
});

test('Sonyflake bytes and base conversion roundtrip', function () {
    $id = Sonyflake::generate();
    $bytes = Sonyflake::toBytes($id);
    $encoded = Sonyflake::toBase($id, 58);

    expect(strlen($bytes))->toBe(8)
        ->and(Sonyflake::fromBytes($bytes))->toBe($id)
        ->and(Sonyflake::fromBase($encoded, 58))->toBe($id);
});

test('Sonyflake config supports output modes', function () {
    $intId = Sonyflake::generateWithConfig(new SonyflakeConfig(outputType: IdOutputType::INT));
    $binaryId = Sonyflake::generateWithConfig(new SonyflakeConfig(outputType: IdOutputType::BINARY));

    expect($intId)->toBeInt()
        ->and($binaryId)->toBeString()
        ->and(strlen($binaryId))->toBe(8);
});
