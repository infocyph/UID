<?php

declare(strict_types=1);

use Infocyph\UID\DeterministicId;
use Infocyph\UID\IdComparator;
use Infocyph\UID\KSUID;
use Infocyph\UID\OpaqueId;
use Infocyph\UID\RandomId;
use Infocyph\UID\XID;

test('KSUID generation and parsing', function () {
    $id = KSUID::generate();
    $parsed = KSUID::parse($id);

    expect(KSUID::isValid($id))->toBeTrue()
        ->and($id)->toHaveLength(27)
        ->and($parsed['time'])->not()->toBeNull();
});

test('KSUID matches the reference text and binary vector', function () {
    $bytes = hex2bin('0669f7efb5a1cd34b5f99d1154fb6853345c9735');
    expect($bytes)->toBeString()
        ->and(KSUID::fromBytes($bytes))->toBe('0ujtsYcgvSTl8PAuAdqWYSMnLOv')
        ->and(KSUID::toBytes('0ujtsYcgvSTl8PAuAdqWYSMnLOv'))->toBe($bytes);
});

test('XID generation and parsing', function () {
    $id = XID::generate();
    $parsed = XID::parse($id);

    expect(XID::isValid($id))->toBeTrue()
        ->and($id)->toHaveLength(20)
        ->and($parsed['time'])->not()->toBeNull();
});

test('XID matches the upstream text and binary vector', function () {
    $bytes = hex2bin('4d88e15b60f486e428412dc9');
    expect($bytes)->toBeString()
        ->and(XID::fromBytes($bytes))->toBe('9m4e2mr0ui3e8a215n4g')
        ->and(XID::toBytes('9m4e2mr0ui3e8a215n4g'))->toBe($bytes);
});

test('Opaque and deterministic IDs', function () {
    $opaque = RandomId::generate(14);
    $det1 = DeterministicId::fromPayload('payload', 20, 'ns');
    $det2 = DeterministicId::fromPayload('payload', 20, 'ns');

    expect($opaque)->toHaveLength(14)
        ->and($det1)->toHaveLength(20)
        ->and($det1)->toBe($det2);
});

test('Random ID rejects lengths outside the ID boundary', function () {
    expect(fn () => RandomId::generate(0))->toThrow(\InvalidArgumentException::class)
        ->and(fn () => RandomId::generate(-1))->toThrow(\InvalidArgumentException::class)
        ->and(fn () => RandomId::generate(1025))->toThrow(\InvalidArgumentException::class);
});

test('IdComparator sorts numeric and lexical values', function () {
    $sortedNumeric = IdComparator::sort(['10', '2', '1']);
    $sortedLexical = IdComparator::sort(['b', 'a', 'c']);

    expect($sortedNumeric)->toBe(['1', '2', '10'])
        ->and($sortedLexical)->toBe(['a', 'b', 'c']);
});

test('KSUID and XID reject text values outside their binary ranges', function () {
    expect(KSUID::isValid(str_repeat('z', 27)))->toBeFalse()
        ->and(fn () => KSUID::toBytes(str_repeat('z', 27)))->toThrow(\Exception::class)
        ->and(XID::isValid(str_repeat('0', 19) . '1'))->toBeFalse();
});

test('KSUID rejects timestamps outside its unsigned 32-bit lifetime', function () {
    expect(fn () => KSUID::generate(new DateTimeImmutable('@1399999999')))
        ->toThrow(\InvalidArgumentException::class)
        ->and(fn () => KSUID::generate(new DateTimeImmutable('@5694967296')))
        ->toThrow(\InvalidArgumentException::class);
});

test('Deterministic IDs enforce canonical namespace and output bounds', function () {
    expect(fn () => DeterministicId::fromPayload('payload', 0))
        ->toThrow(\InvalidArgumentException::class)
        ->and(fn () => DeterministicId::fromPayload('payload', 44))
        ->toThrow(\InvalidArgumentException::class)
        ->and(DeterministicId::fromPayload('payload', 24, 'namespace|is|unambiguous'))
        ->toHaveLength(24);
});

test('Opaque IDs support the complete signed non-negative domain', function () {
    foreach ([0, 1, PHP_INT_MAX] as $value) {
        expect(OpaqueId::toInt(OpaqueId::fromInt($value, 'salt'), 'salt'))->toBe($value);
    }

    expect(fn () => OpaqueId::fromInt(-1))->toThrow(\InvalidArgumentException::class);
});
