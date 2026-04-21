<?php

use Infocyph\UID\DeterministicId;
use Infocyph\UID\IdComparator;
use Infocyph\UID\KSUID;
use Infocyph\UID\OpaqueId;
use Infocyph\UID\XID;

test('KSUID generation and parsing', function () {
    $id = KSUID::generate();
    $parsed = KSUID::parse($id);

    expect(KSUID::isValid($id))->toBeTrue()
        ->and($id)->toHaveLength(27)
        ->and($parsed['isValid'])->toBeTrue()
        ->and($parsed['time'])->not()->toBeNull();
});

test('XID generation and parsing', function () {
    $id = XID::generate();
    $parsed = XID::parse($id);

    expect(XID::isValid($id))->toBeTrue()
        ->and($id)->toHaveLength(20)
        ->and($parsed['isValid'])->toBeTrue()
        ->and($parsed['time'])->not()->toBeNull();
});

test('Opaque and deterministic IDs', function () {
    $opaque = OpaqueId::random(14);
    $det1 = DeterministicId::fromPayload('payload', 20, 'ns');
    $det2 = DeterministicId::fromPayload('payload', 20, 'ns');

    expect($opaque)->toHaveLength(14)
        ->and($det1)->toHaveLength(20)
        ->and($det1)->toBe($det2);
});

test('Opaque ID rejects non-positive lengths', function () {
    expect(fn () => OpaqueId::random(0))->toThrow(\InvalidArgumentException::class)
        ->and(fn () => OpaqueId::random(-1))->toThrow(\InvalidArgumentException::class);
});

test('IdComparator sorts numeric and lexical values', function () {
    $sortedNumeric = IdComparator::sort(['10', '2', '1']);
    $sortedLexical = IdComparator::sort(['b', 'a', 'c']);

    expect($sortedNumeric)->toBe(['1', '2', '10'])
        ->and($sortedLexical)->toBe(['a', 'b', 'c']);
});
