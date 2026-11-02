<?php

declare(strict_types=1);

use Infocyph\UID\UUID;

use function Infocyph\UID\uuid7;
use function Infocyph\UID\uuid2;

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

test('UUID v2 encodes local identity and coordinated node fields', function (int $domain, int $identifier, int $sequence): void {
    $startedAt = time();
    $uuid = UUID::v2($identifier, $domain, 'ABCDEF123456', $sequence);
    $parsed = UUID::parse($uuid);

    expect(UUID::isValid($uuid))->toBeTrue()
        ->and(substr($uuid, 0, 8))->toBe(sprintf('%08x', $identifier))
        ->and(substr($uuid, 19, 4))->toBe(sprintf('%02x%02x', 0x80 | $sequence, $domain))
        ->and($parsed['version'])->toBe(2)
        ->and($parsed['local_domain'])->toBe($domain)
        ->and($parsed['local_identifier'])->toBe($identifier)
        ->and($parsed['node'])->toBe('abcdef123456')
        ->and($parsed['time']?->getTimestamp())->toBeBetween($startedAt - 430, time())
        ->and(UUID::fromBytes(UUID::toBytes($uuid)))->toBe($uuid)
        ->and(UUID::fromBase(UUID::toBase($uuid, 58), 58))->toBe($uuid);
})->with([0, 1, 2])->with([0, 0xffffffff])->with([0, 63]);

test('UUID v2 parses an independent DCE vector without treating the local ID as time', function (): void {
    $uuid = '000001f5-5e9a-21ea-9e00-0242ac130003';
    $parsed = UUID::parse($uuid);
    $value = new \Infocyph\UID\Value\UuidValue($uuid);

    expect($parsed['local_identifier'])->toBe(501)
        ->and($parsed['local_domain'])->toBe(0)
        ->and($parsed['time']?->format('Y-m-d H:i:s.u'))->toBe('2020-03-05 04:30:10.619596')
        ->and($value->getTimestamp()?->format('Y-m-d H:i:s.u'))->toBe('2020-03-05 04:30:10.619596')
        ->and($value->getVersion())->toBe(2)
        ->and($value->isSortable())->toBeFalse();
});

test('UUID v2 uses fresh random nodes and exposes matching convenience APIs', function (): void {
    $ids = [];
    foreach ([UUID::v2(...), \Infocyph\UID\Id::uuid2(...), uuid2(...)] as $generate) {
        for ($index = 0; $index < 100; ++$index) {
            $uuid = $generate(1001);
            $parsed = UUID::parse($uuid);
            expect($parsed['local_identifier'])->toBe(1001)
                ->and($parsed['local_domain'])->toBe(0)
                ->and(hexdec(substr($parsed['node'], 0, 2)) & 1)->toBe(1);
            $ids[] = $uuid;
        }
    }
    expect(array_unique($ids))->toHaveCount(300);
});

test('UUID v2 rejects invalid fields and uncoordinated fixed nodes', function (array $arguments): void {
    expect(fn(): string => UUID::v2(...$arguments))->toThrow(\Infocyph\UID\Exceptions\UUIDException::class);
})->with([
    [[-1]],
    [[0x100000000]],
    [[1, -1]],
    [[1, 3]],
    [[1, 256]],
    [[1, 0, null, -1]],
    [[1, 0, null, 64]],
    [[1, 0, 'abcdef123456']],
    [[1, 0, "abcdef123456\n", 0]],
    [[1, 0, 'invalid-node', 0]],
]);

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

test('UUID time parsing preserves fractional timestamps across the Unix epoch', function (int $version, int $offset, string $expected): void {
    $ticks = str_pad(dechex(0x01b21dd213814000 + $offset), 15, '0', STR_PAD_LEFT);
    $uuid = $version === 1
        ? substr($ticks, -8) . '-' . substr($ticks, -12, 4) . '-1' . substr($ticks, 0, 3) . '-8000-000000000000'
        : substr($ticks, 0, 8) . '-' . substr($ticks, 8, 4) . '-6' . substr($ticks, 12, 3) . '-8000-000000000000';

    expect(UUID::isValid($uuid))->toBeTrue()
        ->and(UUID::parse($uuid)['time']?->format('Y-m-d H:i:s.u'))->toBe($expected)
        ->and((new \Infocyph\UID\Value\UuidValue($uuid))->getTimestamp()?->format('Y-m-d H:i:s.u'))->toBe($expected);
})->with([1, 6])->with([
    [-122_192_928_000_000_000, '1582-10-15 00:00:00.000000'],
    [-15_000_000, '1969-12-31 23:59:58.500000'],
    [-5_000_000, '1969-12-31 23:59:59.500000'],
    [-10, '1969-12-31 23:59:59.999999'],
    [-1, '1969-12-31 23:59:59.999999'],
    [0, '1970-01-01 00:00:00.000000'],
    [5_000_000, '1970-01-01 00:00:00.500000'],
]);

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
