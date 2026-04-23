<?php

use Infocyph\UID\RandomId;

test('CUID2', function () {
    $string = RandomId::cuid2();
    expect($string)
        ->toBeString()
        ->not()->toBeEmpty()
        ->toHaveLength(24)
        ->toMatch('/^[0-9a-z]+$/');
});

test('CUID2 custom length', function () {
    $string = RandomId::cuid2(32);
    expect($string)->toHaveLength(32)->toMatch('/^[0-9a-z]+$/');
});

test('nanoId', function () {
    $string = RandomId::nanoId();
    expect($string)->toBeString()->not()->toBeEmpty()->toHaveLength(21);
});

test('global helper functions for NanoID and CUID2', function () {
    expect(nanoid(10))->toHaveLength(10)
        ->and(cuid2(24))->toHaveLength(24);
});

test('NanoID and CUID2 validation and parse', function () {
    $nano = RandomId::nanoId(12);
    $cuid = RandomId::cuid2(24);

    $nanoParsed = RandomId::parseNanoId($nano, 12);
    $cuidParsed = RandomId::parseCuid2($cuid);

    expect(RandomId::isNanoId($nano, 12))->toBeTrue()
        ->and($nanoParsed['isValid'])->toBeTrue()
        ->and($nanoParsed['length'])->toBe(12)
        ->and($nanoParsed['alphabet'])->toBe('base64url')
        ->and(RandomId::isCuid2($cuid))->toBeTrue()
        ->and($cuidParsed['isValid'])->toBeTrue()
        ->and($cuidParsed['length'])->toBe(24)
        ->and(nanoid_is_valid($nano, 12))->toBeTrue()
        ->and(cuid2_is_valid($cuid))->toBeTrue();
});
