<?php

declare(strict_types=1);

use Infocyph\UID\ObjectID;
use Infocyph\UID\RandomId;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\RandomSampler;
use Infocyph\UID\TypeID;
use Infocyph\UID\UUID;

test('TypeID matches the official v0.3 valid fixtures', function (string $typeId, string $prefix, string $uuid) {
    expect(TypeID::isValid($typeId))->toBeTrue()
        ->and(TypeID::toUuid($typeId))->toBe($uuid)
        ->and(TypeID::fromUuid($prefix, $uuid))->toBe($typeId)
        ->and(TypeID::parse($typeId)['type'])->toBe($prefix);
})->with([
    ['00000000000000000000000000', '', UUID::nil()],
    ['00000000000000000000000001', '', '00000000-0000-0000-0000-000000000001'],
    ['0000000000000000000000000a', '', '00000000-0000-0000-0000-00000000000a'],
    ['0000000000000000000000000g', '', '00000000-0000-0000-0000-000000000010'],
    ['00000000000000000000000010', '', '00000000-0000-0000-0000-000000000020'],
    ['7zzzzzzzzzzzzzzzzzzzzzzzzz', '', UUID::max()],
    ['prefix_0123456789abcdefghjkmnpqrs', 'prefix', '0110c853-1d09-52d8-d73e-1194e95b5f19'],
    ['prefix_01h455vb4pex5vsknk084sn02q', 'prefix', '01890a5d-ac96-774b-bcce-b302099a8057'],
    ['pre_fix_00000000000000000000000000', 'pre_fix', UUID::nil()],
]);

test('TypeID rejects the official v0.3 invalid fixtures', function (string $typeId) {
    expect(TypeID::isValid($typeId))->toBeFalse()
        ->and(fn () => TypeID::parse($typeId))->toThrow(\Infocyph\UID\Exceptions\TypeIDException::class);
})->with([
    'PREFIX_00000000000000000000000000',
    '12345_00000000000000000000000000',
    'pre.fix_00000000000000000000000000',
    'préfix_00000000000000000000000000',
    ' prefix_00000000000000000000000000',
    'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijkl_00000000000000000000000000',
    '_00000000000000000000000000',
    '_',
    'prefix_1234567890123456789012345',
    'prefix_123456789012345678901234567',
    'prefix_1234567890123456789012345 ',
    'prefix_0123456789ABCDEFGHJKMNPQRS',
    'prefix_123456789-123456789-123456',
    'prefix_ooooooiiiiiiuuuuuuulllllll',
    'prefix_i23456789ol23456789oi23456',
    'prefix_123456789-0123456789-0123456',
    'prefix_8zzzzzzzzzzzzzzzzzzzzzzzzz',
    '_prefix_00000000000000000000000000',
    'prefix__00000000000000000000000000',
    '',
    'prefix_',
]);

test('generated TypeIDs contain ordered UUIDv7 values', function () {
    $first = TypeID::generate('event');
    $second = TypeID::generate('event');

    expect(UUID::parse(TypeID::toUuid($first))['version'])->toBe(7)
        ->and(strcmp($second, $first))->toBeGreaterThan(0);
});

test('ObjectID matches unsigned timestamp boundary vectors', function (string $hex, string $expected) {
    $id = ObjectID::fromBytes(hex2bin($hex . str_repeat('00', 8)) ?: '');

    expect(ObjectID::parse($id)['time']->format('Y-m-d H:i:s'))->toBe($expected)
        ->and(ObjectID::toBytes($id))->toBe(hex2bin($hex . str_repeat('00', 8)));
})->with([
    ['00000000', '1970-01-01 00:00:00'],
    ['7fffffff', '2038-01-19 03:14:07'],
    ['80000000', '2038-01-19 03:14:08'],
    ['ffffffff', '2106-02-07 06:28:15'],
]);

test('ObjectID generation is canonical and increments its counter', function () {
    $first = ObjectID::generate();
    $second = ObjectID::generate();

    expect($first)->toMatch('/^[0-9a-f]{24}$/')
        ->and(ObjectID::parse($second)['counter'])
        ->toBe((ObjectID::parse($first)['counter'] + 1) & 0xffffff);
});

test('random sampler maps every accepted byte uniformly', function (int $size) {
    $alphabet = '';
    for ($index = 0; $index < $size; ++$index) {
        $alphabet .= chr($index);
    }

    $bytes = '';
    for ($index = 0; $index < 256; ++$index) {
        $bytes .= chr($index);
    }

    $mapped = RandomSampler::mapBytes($bytes, $alphabet);
    $expectedPerSymbol = intdiv(256, $size);
    $counts = count_chars($mapped, 1);
    for ($index = 0; $index < $size; ++$index) {
        expect($counts[$index] ?? 0)->toBe($expectedPerSymbol);
    }
})->with([2, 3, 16, 32, 61, 62, 64, 255, 256]);

test('RandomId validates alphabets and output bounds', function () {
    $id = RandomId::generate(1024);

    expect(RandomId::isValid($id, 1024))->toBeTrue()
        ->and(fn () => RandomId::generate(10, 'aa'))->toThrow(\InvalidArgumentException::class)
        ->and(fn () => RandomId::generate(10, 'a'))->toThrow(\InvalidArgumentException::class);
});

test('byte radix codecs round trip zero and fixed-width boundary values', function () {
    foreach ([8, 10, 12, 16, 20, 32] as $length) {
        foreach ([str_repeat("\0", $length), str_repeat("\xff", $length)] as $bytes) {
            foreach ([16, 32, 36, 58, 62] as $base) {
                $encoded = BaseEncoder::encodeBytes($bytes, $base);
                expect(BaseEncoder::decodeToBytes($encoded, $base, $length))->toBe($bytes);
            }
        }
    }

    expect(BaseEncoder::encodeBytes(str_repeat("\0", 16), 58))->toBe('1');
});

test('the installed runtime satisfies the 64-bit package contract', function () {
    expect(PHP_INT_SIZE)->toBe(8);
});


test('v5 public helper and facade parameter names remain stable', function (): void {
    $functions = [
        'Infocyph\\UID\\cuid2' => ['length'],
        'Infocyph\\UID\\ksuid' => ['dateTime'],
        'Infocyph\\UID\\nano_id' => ['length'],
        'Infocyph\\UID\\object_id' => ['dateTime'],
        'Infocyph\\UID\\randflake' => ['config'],
        'Infocyph\\UID\\random_id' => ['length', 'alphabet'],
        'Infocyph\\UID\\snowflake' => ['config'],
        'Infocyph\\UID\\sonyflake' => ['config'],
        'Infocyph\\UID\\tbsl' => ['config'],
        'Infocyph\\UID\\type_id' => ['type'],
        'Infocyph\\UID\\ulid' => ['dateTime', 'mode'],
        'Infocyph\\UID\\uuid1' => ['node'],
        'Infocyph\\UID\\uuid3' => ['namespace', 'string'],
        'Infocyph\\UID\\uuid4' => [],
        'Infocyph\\UID\\uuid5' => ['namespace', 'string'],
        'Infocyph\\UID\\uuid6' => ['node'],
        'Infocyph\\UID\\uuid7' => ['dateTime'],
        'Infocyph\\UID\\uuid8' => ['node'],
        'Infocyph\\UID\\xid' => [],
    ];

    foreach ($functions as $function => $expected) {
        $actual = array_map(
            static fn(ReflectionParameter $parameter): string => $parameter->getName(),
            (new ReflectionFunction($function))->getParameters(),
        );
        expect($actual)->toBe($expected);
    }

    $methods = [
        'cuid2' => ['length'],
        'deterministic' => ['payload', 'length', 'namespace'],
        'ksuid' => ['dateTime'],
        'nanoId' => ['length'],
        'objectId' => ['dateTime'],
        'randflake' => ['config'],
        'random' => ['length', 'alphabet'],
        'snowflake' => ['config'],
        'snowflakeValue' => ['config'],
        'sonyflake' => ['config'],
        'sonyflakeValue' => ['config'],
        'tbsl' => ['config'],
        'typeId' => ['type'],
        'ulid' => ['dateTime', 'mode'],
        'uuid' => ['dateTime'],
        'uuid1' => ['node'],
        'uuid3' => ['namespace', 'string'],
        'uuid4' => [],
        'uuid5' => ['namespace', 'string'],
        'uuid6' => ['node'],
        'uuid7' => ['dateTime'],
        'uuid8' => ['node'],
        'xid' => [],
    ];

    foreach ($methods as $method => $expected) {
        $actual = array_map(
            static fn(ReflectionParameter $parameter): string => $parameter->getName(),
            (new ReflectionMethod(\Infocyph\UID\Id::class, $method))->getParameters(),
        );
        expect($actual)->toBe($expected);
    }
});

test('v5 configuration constructor parameters remain an unchanged prefix', function (): void {
    $contracts = [
        \Infocyph\UID\Configuration\SnowflakeConfig::class => [
            'datacenterId', 'workerId', 'nodeResolver', 'customEpoch',
            'sequenceProvider', 'clockBackwardPolicy',
        ],
        \Infocyph\UID\Configuration\SonyflakeConfig::class => [
            'machineId', 'machineIdResolver', 'customEpoch',
            'sequenceProvider', 'clockBackwardPolicy',
        ],
        \Infocyph\UID\Configuration\TBSLConfig::class => [
            'machineId', 'sequenced', 'machineIdResolver',
            'sequenceProvider', 'clockBackwardPolicy',
        ],
        \Infocyph\UID\Configuration\RandflakeConfig::class => [
            'nodeId', 'leaseStart', 'leaseEnd', 'secret', 'sequenceProvider',
        ],
    ];

    foreach ($contracts as $class => $expectedPrefix) {
        $constructor = (new ReflectionClass($class))->getConstructor();
        expect($constructor)->not->toBeNull();

        $names = array_map(
            static fn(ReflectionParameter $parameter): string => $parameter->getName(),
            $constructor?->getParameters() ?? [],
        );
        expect(array_slice($names, 0, count($expectedPrefix)))->toBe($expectedPrefix);
    }
});

test('legacy Snowflake and Sonyflake stored-layout vectors still parse identically', function (): void {
    $epoch = 1_577_836_800_000;
    $snowflake = (string) ((1 << 22) | (2 << 17) | (3 << 12) | 4);
    $snowflakeParts = \Infocyph\UID\Snowflake::parseWithEpoch($snowflake, $epoch);

    expect($snowflakeParts['sequence'])->toBe(4)
        ->and($snowflakeParts['worker_id'])->toBe(3)
        ->and($snowflakeParts['datacenter_id'])->toBe(2)
        ->and($snowflakeParts['time']->format('Uv'))->toBe((string) ($epoch + 1));

    $sonyflake = (string) ((1 << 24) | (42 << 8) | 1);
    $sonyflakeParts = \Infocyph\UID\Sonyflake::parseWithEpoch($sonyflake, $epoch);

    expect($sonyflakeParts['sequence'])->toBe(1)
        ->and($sonyflakeParts['machine_id'])->toBe(42)
        ->and($sonyflakeParts['time']->format('Uv'))->toBe((string) ($epoch + 10));
});
