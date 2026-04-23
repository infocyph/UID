<?php

use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\TBSL;

test('TBSL Basic Functionality', function () {
    $startedAt = time() - 1;
    $sf = TBSL::generate();
    $finishedAt = time() + 1;
    $parsed = TBSL::parse($sf);
    expect($parsed['isValid'])->toBeTrue()
        ->and($parsed['time']->getTimestamp())->toBeBetween($startedAt, $finishedAt)
        ->and($parsed['machineId'])->toBe(0);
});

test('TBSL ID Uniqueness', function () {
    $id1 = TBSL::generate(0, true);
    $id2 = TBSL::generate(0, true);

    expect($id1)->not->toBe($id2);
});

test('TBSL Sequential Order', function () {
    $id1 = TBSL::generate(0, true);
    $id2 = TBSL::generate(0, true);

    expect(strcmp($id2, $id1))->toBeGreaterThan(0);
});

test('TBSL Machine ID Differentiation', function () {
    $id1 = TBSL::generate(1);
    $id2 = TBSL::generate(2);

    $parsed1 = TBSL::parse($id1);
    $parsed2 = TBSL::parse($id2);

    expect($parsed1['machineId'])->not->toBe($parsed2['machineId']);
});

test('TBSL rejects invalid machine IDs', function () {
    expect(fn () => TBSL::generate(-1))->toThrow(\Infocyph\UID\Exceptions\UIDException::class)
        ->and(fn () => TBSL::generate(100))->toThrow(\Infocyph\UID\Exceptions\UIDException::class);
});

test('TBSL bytes conversion roundtrip', function () {
    $id = TBSL::generate();
    $bytes = TBSL::toBytes($id);

    expect(strlen($bytes))->toBe(10)
        ->and(TBSL::fromBytes($bytes))->toBe($id);
});

test('TBSL validation helper', function () {
    $id = TBSL::generate();

    expect(TBSL::isValid($id))->toBeTrue()
        ->and(TBSL::isValid('ZZZZ'))->toBeFalse();
});

test('TBSL base conversion roundtrip', function () {
    $id = TBSL::generate();
    $encoded = TBSL::toBase($id, 62);

    expect(TBSL::fromBase($encoded, 62))->toBe($id);
});

test('TBSL config supports output mode', function () {
    $binary = TBSL::generateWithConfig(new TBSLConfig(outputType: IdOutputType::BINARY));

    expect($binary)->toBeString()
        ->and(strlen($binary))->toBe(10);
});
