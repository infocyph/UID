<?php

declare(strict_types=1);

use Infocyph\UID\UUID;

use function Infocyph\UID\uuid7;

test('UUID v1', function () {
    $startedAt = time() - 1;
    $uid = UUID::v1();
    $finishedAt = time() + 1;
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['version'])->toBe(1)
        ->and($parsed['time'])->not()->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull()
        ->and($parsed['time']->getTimestamp())->toBeBetween($startedAt, $finishedAt);
});

$ns = UUID::v4();

test('UUID v3', function () use ($ns) {
    $uid = UUID::v3($ns, 'my-string');
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['version'])->toBe(3)
        ->and($parsed['time'])->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull();
});

test('UUID v4', function () {
    $uid = UUID::v4();
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['version'])->toBe(4)
        ->and($parsed['time'])->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull();
});

test('UUID v5', function () use ($ns) {
    $uid = UUID::v5($ns, 'my-string');
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['version'])->toBe(5)
        ->and($parsed['time'])->toBeNull()
        ->and($parsed['node'])->toBeString()->not()->toBeNull();
});

test('UUID v6', function () {
    $startedAt = time() - 1;
    $uid = UUID::v6();
    $finishedAt = time() + 1;
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['version'])->toBe(6)
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
    expect($parsed['version'])->toBe(7)
        ->and($parsed['time'])->not()->toBeNull()
        ->and($parsed['node'])->toBeNull()
        ->and($parsed['tail'])->toBeString()->not()->toBeNull()
        ->and($parsed['time']->getTimestamp())->toBeBetween($startedAt, $finishedAt);
});

test('UUID v8', function () {
    $uid = UUID::v8();
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['version'])->toBe(8)
        ->and($parsed['time'])->toBeNull()
        ->and($parsed['node'])->toBeNull()
        ->and($parsed['tail'])->toBeString()->not()->toBeNull();
});

test('UUID node must be exactly 12 hex characters when provided', function () {
    expect(fn () => UUID::v1('zzzzzzzzzzzz'))->toThrow(\Infocyph\UID\Exceptions\UUIDException::class)
        ->and(fn () => UUID::v6('0123456789abcdef'))->toThrow(\Infocyph\UID\Exceptions\UUIDException::class)
        ->and(fn () => UUID::v8('123'))->toThrow(\Infocyph\UID\Exceptions\UUIDException::class);
});

test('UUID node accepts uppercase hex input and normalizes output', function () {
    $node = 'ABCDEF123456';
    $normalizedNode = strtolower($node);

    $ids = [
        UUID::v1($node),
        UUID::v6($node),
        UUID::v8($node),
    ];

    foreach ($ids as $id) {
        expect(UUID::isValid($id))->toBeTrue()
            ->and(str_ends_with($id, $normalizedNode))->toBeTrue();
    }
});

test('generated UUID nodes set the multicast bit', function () {
    $node = UUID::getNode();
    $uuid = UUID::v1();
    $parsedNode = UUID::parse($uuid)['node'];

    expect(hexdec(substr($node, 0, 2)) & 0x01)->toBe(0x01)
        ->and($parsedNode)->toBeString()
        ->and(hexdec(substr($parsedNode, 0, 2)) & 0x01)->toBe(0x01);
});

test('GUID', function () {
    $uid = UUID::guid();
    expect($uid)->toBeString();
    $parsed = UUID::parse($uid);
    expect($parsed['version'])->toBe(4)
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

test('namespaced UUID helper delegates directly to UUID', function () {
    expect(UUID::parse(uuid7())['version'])->toBe(7);
});

test('UUID RFC name-based vectors match independently published values', function () {
    expect(UUID::v3('dns', 'www.widgets.com'))->toBe('3d813cbb-47fb-32ba-91df-831e1593ac29')
        ->and(UUID::v5('dns', 'www.widgets.com'))->toBe('21f7f8de-8051-5b89-8680-0195ef798b6a');
});

test('generic UUID v8 parsing does not infer an application timestamp', function () {
    $parsed = UUID::parse('00000000-0000-8000-8000-000000000000');

    expect($parsed['version'])->toBe(8)
        ->and($parsed['time'])->toBeNull()
        ->and((new \Infocyph\UID\Value\UuidValue('00000000-0000-8000-8000-000000000000'))->isSortable())
        ->toBeFalse();
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

test('UUID validation rejects reserved versions and non-RFC variants', function () {
    expect(UUID::isValid('00000000-0000-9000-8000-000000000000'))->toBeFalse()
        ->and(UUID::isValid('00000000-0000-4000-c000-000000000000'))->toBeFalse()
        ->and(UUID::isValid(UUID::nil()))->toBeTrue()
        ->and(UUID::isValid(UUID::max()))->toBeTrue();
});

test('UUID v7 rejects timestamps outside its unsigned 48-bit field', function () {
    expect(fn () => UUID::v7(new DateTimeImmutable('@-1')))
        ->toThrow(\Infocyph\UID\Exceptions\UUIDException::class)
        ->and(fn () => UUID::v7(new DateTimeImmutable('@281474976711')))
        ->toThrow(\Infocyph\UID\Exceptions\UUIDException::class);
});
