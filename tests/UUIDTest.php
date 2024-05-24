<?php

use Infocyph\UID\UUID;

test('UUID v1', function () {
    $uid = UUID::v1();
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(1)
        ->and($parsed['time'])->not()->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull()
        ->and($parsed['time']->getTimestamp())->toBeBetween(time() - 1, time() + 1);
});

$ns = UUID::v4();

test('UUID v3', function () use ($ns) {
    $uid = UUID::v3($ns, 'my-string');
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(3)
        ->and($parsed['time'])->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull();
});

test('UUID v4', function () {
    $uid = UUID::v4();
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(4)
        ->and($parsed['time'])->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull();
});

test('UUID v5', function () use ($ns) {
    $uid = UUID::v5($ns, 'my-string');
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(5)
        ->and($parsed['time'])->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull();
});

test('UUID v6', function () {
    $uid = UUID::v6();
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(6)
        ->and($parsed['time'])->not()->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull()
        ->and($parsed['time']->getTimestamp())->toBeBetween(time() - 1, time() + 1);
});

test('UUID v7', function () {
    $uid = UUID::v7();
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(7)
        ->and($parsed['time'])->not()->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull()
        ->and($parsed['time']->getTimestamp())->toBeBetween(time() - 1, time() + 1);
});

test('UUID v8', function () {
    $uid = UUID::v8();
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(8)
        ->and($parsed['time'])->not()->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull()
        ->and($parsed['time']->getTimestamp())->toBeBetween(time() - 1, time() + 1);
});

