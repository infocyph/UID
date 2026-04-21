<?php

use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Snowflake;

test('Snowflake Basic Functionality', function () {
    $startedAt = time() - 1;
    $sf = Snowflake::generate();
    $finishedAt = time() + 1;
    $parsed = Snowflake::parse($sf);

    expect($parsed['time']->getTimestamp())->toBeBetween($startedAt, $finishedAt)
        ->and($parsed['worker_id'])->toBe(0)
        ->and($parsed['datacenter_id'])->toBe(0);
});

test('Snowflake ID Uniqueness', function () {
    $ids = [];
    for ($i = 0; $i < 100; $i++) {
        $ids[] = Snowflake::generate();
    }

    expect(count(array_unique($ids)))->toBe(count($ids));
});

test('Snowflake Sequential Order', function () {
    $previous = (int) Snowflake::generate();
    for ($i = 0; $i < 100; $i++) {
        $current = (int) Snowflake::generate();
        expect($current)->toBeGreaterThan($previous);
        $previous = $current;
    }
});

test('Snowflake Datacenter and Worker Differentiation', function () {
    $id1 = Snowflake::generate(1, 1);
    $id2 = Snowflake::generate(2, 2);

    $parsed1 = Snowflake::parse($id1);
    $parsed2 = Snowflake::parse($id2);

    expect($parsed1['worker_id'])->not->toBe($parsed2['worker_id'])
        ->and($parsed1['datacenter_id'])->not->toBe($parsed2['datacenter_id']);
});

test('Snowflake Max Sequence Handling', function () {
    $maxSeq = (-1 ^ (-1 << 12));

    $id1 = Snowflake::generate();
    for ($i = 0; $i <= $maxSeq; $i++) {
        $id1 = Snowflake::generate();
    }
    $id2 = Snowflake::generate();

    expect((int) $id2)->toBeGreaterThan((int) $id1);
});

test('Snowflake uses distinct sequence files per worker combination', function () {
    $sequenceKeyA = (1 << 5) | 2;
    $sequenceKeyB = (3 << 5) | 4;

    $fileA = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "uid-snowflake-$sequenceKeyA.seq";
    $fileB = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "uid-snowflake-$sequenceKeyB.seq";

    if (file_exists($fileA)) {
        unlink($fileA);
    }
    if (file_exists($fileB)) {
        unlink($fileB);
    }

    Snowflake::generate(1, 2);
    Snowflake::generate(3, 4);

    expect(file_exists($fileA))->toBeTrue()
        ->and(file_exists($fileB))->toBeTrue();

    if (file_exists($fileA)) {
        unlink($fileA);
    }
    if (file_exists($fileB)) {
        unlink($fileB);
    }
});

test('Snowflake sequence key does not collide for ambiguous decimal concatenations', function () {
    $sequenceKeyA = (1 << 5) | 23;
    $sequenceKeyB = (12 << 5) | 3;

    $fileA = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "uid-snowflake-$sequenceKeyA.seq";
    $fileB = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "uid-snowflake-$sequenceKeyB.seq";

    if (file_exists($fileA)) {
        unlink($fileA);
    }
    if (file_exists($fileB)) {
        unlink($fileB);
    }

    Snowflake::generate(1, 23);
    Snowflake::generate(12, 3);

    expect(file_exists($fileA))->toBeTrue()
        ->and(file_exists($fileB))->toBeTrue();

    if (file_exists($fileA)) {
        unlink($fileA);
    }
    if (file_exists($fileB)) {
        unlink($fileB);
    }
});

test('Snowflake validation helper', function () {
    $id = Snowflake::generate();

    expect(Snowflake::isValid($id))->toBeTrue()
        ->and(Snowflake::isValid('abc'))->toBeFalse()
        ->and(Snowflake::isValid('0'))->toBeFalse();
});

test('Snowflake bytes and base conversion roundtrip', function () {
    $id = Snowflake::generate();
    $bytes = Snowflake::toBytes($id);
    $encoded = Snowflake::toBase($id, 36);

    expect(strlen($bytes))->toBe(8)
        ->and(Snowflake::fromBytes($bytes))->toBe($id)
        ->and(Snowflake::fromBase($encoded, 36))->toBe($id);
});

test('Snowflake config supports output modes', function () {
    $intId = Snowflake::generateWithConfig(new SnowflakeConfig(outputType: IdOutputType::INT));
    $binaryId = Snowflake::generateWithConfig(new SnowflakeConfig(outputType: IdOutputType::BINARY));

    expect($intId)->toBeInt()
        ->and($binaryId)->toBeString()
        ->and(strlen($binaryId))->toBe(8);
});
