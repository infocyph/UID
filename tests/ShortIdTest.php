<?php

use Infocyph\UID\Contracts\IdAlgorithmInterface;
use Infocyph\UID\CUID2;
use Infocyph\UID\NanoID;

test('CUID2', function () {
    $string = CUID2::generate();
    expect($string)
        ->toBeString()
        ->not()->toBeEmpty()
        ->toHaveLength(24)
        ->toMatch('/^[0-9a-z]+$/');
});

test('CUID2 custom length', function () {
    $string = CUID2::generate(32);
    expect($string)->toHaveLength(32)->toMatch('/^[0-9a-z]+$/');
});

test('nanoId', function () {
    $string = NanoID::generate();
    expect($string)->toBeString()->not()->toBeEmpty()->toHaveLength(21);
});

test('global helper functions for NanoID and CUID2', function () {
    expect(nanoid(10))->toHaveLength(10)
        ->and(cuid2(24))->toHaveLength(24);
});

test('NanoID and CUID2 validation and parse', function () {
    $nano = NanoID::generate(12);
    $cuid = CUID2::generate(24);

    $nanoParsed = NanoID::parse($nano, 12);
    $cuidParsed = CUID2::parse($cuid);

    expect(NanoID::isValid($nano, 12))->toBeTrue()
        ->and($nanoParsed['isValid'])->toBeTrue()
        ->and($nanoParsed['length'])->toBe(12)
        ->and($nanoParsed['alphabet'])->toBe('base64url')
        ->and(CUID2::isValid($cuid))->toBeTrue()
        ->and($cuidParsed['isValid'])->toBeTrue()
        ->and($cuidParsed['length'])->toBe(24)
        ->and(nanoid_is_valid($nano, 12))->toBeTrue()
        ->and(cuid2_is_valid($cuid))->toBeTrue();
});
