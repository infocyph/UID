<?php

declare(strict_types=1);

use Infocyph\UID\CUID2;
use Infocyph\UID\NanoID;

use function Infocyph\UID\cuid2;
use function Infocyph\UID\nano_id;

test('CUID2', function () {
    $string = CUID2::generate();
    expect($string)
        ->toBeString()
        ->not()->toBeEmpty()
        ->toHaveLength(24)
        ->toMatch('/^[a-z][0-9a-z]+$/');
});

test('CUID2 custom length', function () {
    $string = CUID2::generate(32);
    expect($string)->toHaveLength(32)->toMatch('/^[0-9a-z]+$/');
});

test('nanoId', function () {
    $string = NanoID::generate();
    expect($string)->toBeString()->not()->toBeEmpty()->toHaveLength(21);
});

test('namespaced helper functions for NanoID and CUID2', function () {
    expect(nano_id(10))->toHaveLength(10)
        ->and(cuid2(24))->toHaveLength(24);
});

test('NanoID and CUID2 validation and parse', function () {
    $nano = NanoID::generate(12);
    $cuid = CUID2::generate(24);

    $nanoParsed = NanoID::parse($nano, 12);
    $cuidParsed = CUID2::parse($cuid);

    expect(NanoID::isValid($nano, 12))->toBeTrue()
        ->and($nanoParsed['length'])->toBe(12)
        ->and($nanoParsed['alphabet'])->toBe('base64url')
        ->and(CUID2::isValid($cuid))->toBeTrue()
        ->and($cuidParsed['length'])->toBe(24);
});

test('CUID2 uses canonical first-letter and length boundaries', function () {
    $minimum = CUID2::generate(2);

    expect($minimum)->toMatch('/^[a-z][0-9a-z]$/')
        ->and(CUID2::isValid('1abc'))->toBeFalse()
        ->and(fn () => CUID2::generate(1))->toThrow(\InvalidArgumentException::class)
        ->and(fn () => CUID2::generate(33))->toThrow(\InvalidArgumentException::class);
});
