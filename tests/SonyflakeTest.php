<?php

use Infocyph\UID\Sonyflake;

test('Sonyflake Basic Functionality', function () {
    $sf = Sonyflake::generate();
    $parsed = Sonyflake::parse($sf);

    expect($parsed['time']->getTimestamp())->toBeBetween(time() - 1, time())
        ->and($parsed['machine_id'])->toBe(0);
});

test('Sonyflake ID Uniqueness', function () {
    $id1 = Sonyflake::generate();
    usleep(10);
    $id2 = Sonyflake::generate();

    expect($id1)->not->toBe($id2);
});

test('Sonyflake Sequential Order', function () {
    $id1 = Sonyflake::generate();
    usleep(10);
    $id2 = Sonyflake::generate();

    expect((int) $id2)->toBeGreaterThan((int) $id1);
});

test('Sonyflake Machine ID Differentiation', function () {
    $id1 = Sonyflake::generate(1);
    $id2 = Sonyflake::generate(2);

    $parsed1 = Sonyflake::parse($id1);
    $parsed2 = Sonyflake::parse($id2);

    expect($parsed1['machine_id'])->not->toBe($parsed2['machine_id']);
});

test('Sonyflake Max Sequence Handling', function () {
    $maxSeq = (-1 ^ (-1 << 8));

    $id1 = Sonyflake::generate();
    for ($i = 0; $i <= $maxSeq; $i++) {
        $id1 = Sonyflake::generate();
    }
    usleep(10);
    $id2 = Sonyflake::generate();

    expect((int) $id2)->toBeGreaterThan((int) $id1);
});

