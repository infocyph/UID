<?php

use Infocyph\UID\ULID;
use Infocyph\UID\Enums\UlidGenerationMode;
use Infocyph\UID\Exceptions\ULIDException;

test('Basic', function () {
    $startedAt = time() - 1;
    $ulid = ULID::generate();
    $finishedAt = time() + 1;
    expect($ulid)->toBeString()
        ->and(ULID::isValid($ulid))->toBeTrue()
        ->and(ULID::getTime($ulid)->getTimestamp())->toBeBetween($startedAt, $finishedAt);
});

test('Monotonic overflow on fixed timestamp throws ULIDException', function () {
    $dateTime = DateTimeImmutable::createFromFormat('U.u', '1700000000.123000');
    expect($dateTime)->not()->toBeFalse();

    $class = new ReflectionClass(ULID::class);
    $lastGenTime = $class->getProperty('lastGenTime');
    $lastRandChars = $class->getProperty('lastRandChars');
    $lastGenTime->setAccessible(true);
    $lastRandChars->setAccessible(true);

    $previousTime = $lastGenTime->getValue(null);
    $previousChars = $lastRandChars->getValue(null);

    try {
        $lastGenTime->setValue(null, (int)$dateTime->format('Uv'));
        $lastRandChars->setValue(null, array_fill(0, 16, 31));
        expect(fn() => ULID::generate($dateTime))->toThrow(ULIDException::class);
    } finally {
        $lastGenTime->setValue(null, $previousTime);
        $lastRandChars->setValue(null, $previousChars);
    }
});

test('ULID bytes conversion roundtrip', function () {
    $ulid = ULID::generate();
    $bytes = ULID::toBytes($ulid);

    expect(strlen($bytes))->toBe(16)
        ->and(ULID::fromBytes($bytes))->toBe($ulid);
});

test('ULID random mode does not depend on monotonic state', function () {
    $fixed = DateTimeImmutable::createFromFormat('U.u', '1700000000.123000');
    expect($fixed)->not()->toBeFalse();

    $id1 = ULID::generate($fixed, UlidGenerationMode::RANDOM);
    $id2 = ULID::generate($fixed, UlidGenerationMode::RANDOM);

    expect($id1)->not()->toBe($id2);
});

test('ULID base conversion roundtrip', function () {
    $ulid = ULID::generate();
    $encoded = ULID::toBase($ulid, 62);

    expect(ULID::fromBase($encoded, 62))->toBe($ulid);
});
