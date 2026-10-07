<?php

declare(strict_types=1);

use Infocyph\UID\Support\DecimalBytes;
use Infocyph\UID\Support\Sparx64;

test('signed 64-bit decimal conversion preserves boundary bit patterns', function (): void {
    foreach ([
        '0',
        '9223372036854775807',
        '-1',
        '-9223372036854775808',
    ] as $value) {
        expect(DecimalBytes::fromLittleEndianSigned64(
            DecimalBytes::toLittleEndianSigned64($value),
        ))->toBe($value);
    }
});

test('SPARX64 encryption round trips fixed blocks', function (): void {
    $cipher = new Sparx64(hex2bin('000102030405060708090a0b0c0d0e0f'));
    $plain = hex2bin('0011223344556677');
    $encrypted = $cipher->encrypt($plain);

    expect($encrypted)->not()->toBe($plain)
        ->and($cipher->decrypt($encrypted))->toBe($plain);
});
