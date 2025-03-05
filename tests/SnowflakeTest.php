<?php

use Infocyph\UID\Snowflake;

test('Snowflake Basic Functionality', function () {
    $sf = Snowflake::generate();
    $parsed = Snowflake::parse($sf);

    expect($parsed['time']->getTimestamp())->toBeBetween(time() - 1, time())
        ->and($parsed['worker_id'])->toBe(0)
        ->and($parsed['datacenter_id'])->toBe(0);
});

test('Snowflake ID Uniqueness', function () {
    $id1 = Snowflake::generate();
    usleep(10);
    $id2 = Snowflake::generate();

    expect($id1)->not->toBe($id2);
});

test('Snowflake Sequential Order', function () {
    $id1 = Snowflake::generate();
    usleep(10);
    $id2 = Snowflake::generate();

    expect((int) $id2)->toBeGreaterThan((int) $id1);
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
    usleep(10);
    $id2 = Snowflake::generate();

    expect((int) $id2)->toBeGreaterThan((int) $id1);
});


