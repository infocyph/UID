<?php

use Infocyph\UID\TBSL;

test('TBSL Basic Functionality', function () {
    $sf = TBSL::generate();
    $parsed = TBSL::parse($sf);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['time']->getTimestamp())->toBeBetween(time() - 1, time())
        ->and($parsed['machineId'])->toBe(0);
});

test('TBSL ID Uniqueness', function () {
    $id1 = TBSL::generate();
    usleep(10); // Ensure slight time difference
    $id2 = TBSL::generate();

    expect($id1)->not->toBe($id2);
});

test('TBSL Sequential Order', function () {
    $id1 = TBSL::generate();
    usleep(10);
    $id2 = TBSL::generate();

    expect(hexdec($id2))->toBeGreaterThan(hexdec($id1));
});

test('TBSL Machine ID Differentiation', function () {
    $id1 = TBSL::generate(1);
    $id2 = TBSL::generate(2);

    $parsed1 = TBSL::parse($id1);
    $parsed2 = TBSL::parse($id2);

    expect($parsed1['machineId'])->not->toBe($parsed2['machineId']);
});

