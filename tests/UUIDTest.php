<?php

use Infocyph\UID\UUID;

test('UUID v1', function () {
    $startedAt = time() - 1;
    $uid = UUID::v1();
    $finishedAt = time() + 1;
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(1)
        ->and($parsed['time'])->not()->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull()
        ->and($parsed['time']->getTimestamp())->toBeBetween($startedAt, $finishedAt);
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
    $startedAt = time() - 1;
    $uid = UUID::v6();
    $finishedAt = time() + 1;
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(6)
        ->and($parsed['time'])->not()->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull()
        ->and($parsed['time']->getTimestamp())->toBeBetween($startedAt, $finishedAt);
});

test('UUID v7', function () {
    $startedAt = time() - 1;
    $uid = UUID::v7();
    $finishedAt = time() + 1;
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(7)
        ->and($parsed['time'])->not()->toBeNull()
        ->and($parsed['node'])->toBeNull()
        ->and($parsed['tail'])->toBeString()->not()->toBeNull()
        ->and($parsed['time']->getTimestamp())->toBeBetween($startedAt, $finishedAt);
});

test('UUID v8', function () {
    $startedAt = time() - 1;
    $uid = UUID::v8();
    $finishedAt = time() + 1;
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(8)
        ->and($parsed['time'])->not()->toBeNull()
        ->and($parsed['node'])->toBeNull()
        ->and($parsed['tail'])->toBeString()->not()->toBeNull()
        ->and($parsed['time']->getTimestamp())->toBeBetween($startedAt, $finishedAt);
});

test('GUID', function () {
    $uid = UUID::guid();
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['version'])->toBe(4)
        ->and($parsed['time'])->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull();
});

test('UUID v7 does not move timestamp forward for monotonicity', function () {
    $fixedTime = DateTimeImmutable::createFromFormat('U.u', '1700000000.123000');
    expect($fixedTime)->not()->toBeFalse();

    $uid1 = UUID::v7($fixedTime);
    $uid2 = UUID::v7($fixedTime);

    $time1 = (int)UUID::parse($uid1)['time']->format('Uv');
    $time2 = (int)UUID::parse($uid2)['time']->format('Uv');
    $expected = (int)$fixedTime->format('Uv');

    expect($time1)->toBe($expected)
        ->and($time2)->toBe($expected)
        ->and($uid1)->not()->toBe($uid2);
});

test('UUID nil and max helpers', function () {
    expect(UUID::nil())->toBe('00000000-0000-0000-0000-000000000000')
        ->and(UUID::max())->toBe('ffffffff-ffff-ffff-ffff-ffffffffffff')
        ->and(UUID::isNil(UUID::nil()))->toBeTrue()
        ->and(UUID::isMax(UUID::max()))->toBeTrue()
        ->and(UUID::isNil(UUID::max()))->toBeFalse()
        ->and(UUID::isMax(UUID::nil()))->toBeFalse();
});

test('UUID canonical transformation helpers', function () {
    $uuid = UUID::v4();
    $upperBraced = '{' . strtoupper($uuid) . '}';

    expect(UUID::normalize($upperBraced))->toBe(strtolower($uuid))
        ->and(UUID::compact($uuid))->toHaveLength(32)
        ->and(UUID::toUrn($uuid))->toBe('urn:uuid:' . strtolower($uuid))
        ->and(UUID::toBraces($uuid))->toBe('{' . strtolower($uuid) . '}')
        ->and(UUID::lowercase(strtoupper($uuid)))->toBe(strtolower($uuid))
        ->and(UUID::uppercase($uuid))->toBe(strtoupper($uuid));
});

test('UUID bytes conversion roundtrip', function () {
    $uuid = UUID::v4();
    $bytes = UUID::toBytes($uuid);

    expect(strlen($bytes))->toBe(16)
        ->and(UUID::fromBytes($bytes))->toBe(strtolower($uuid));
});

test('global UUID helper transformations', function () {
    $uuid = UUID::v4();

    expect(uuid_nil())->toBe(UUID::nil())
        ->and(uuid_max())->toBe(UUID::max())
        ->and(uuid_is_nil(UUID::nil()))->toBeTrue()
        ->and(uuid_is_max(UUID::max()))->toBeTrue()
        ->and(uuid_normalize(strtoupper($uuid)))->toBe(strtolower($uuid))
        ->and(uuid_compact($uuid))->toHaveLength(32)
        ->and(uuid_urn($uuid))->toBe('urn:uuid:' . strtolower($uuid))
        ->and(uuid_braces($uuid))->toBe('{' . strtolower($uuid) . '}');
});

test('UUID base conversion roundtrip', function () {
    $uuid = UUID::v4();
    $encoded = UUID::toBase($uuid, 58);

    expect(UUID::fromBase($encoded, 58))->toBe(strtolower($uuid));
});

test('UUID single-file API covers generate, parse, and byte conversion', function () {
    $uuid = UUID::v7();
    $parsed = UUID::parse($uuid);
    $bytes = UUID::toBytes($uuid);

    expect(UUID::isValid($uuid))->toBeTrue()
        ->and($parsed['version'])->toBe(7)
        ->and(UUID::fromBytes($bytes))->toBe(strtolower($uuid));
});
