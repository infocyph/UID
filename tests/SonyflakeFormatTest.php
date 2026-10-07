<?php

declare(strict_types=1);

use Infocyph\UID\Enums\SonyflakeFormat;
use Infocyph\UID\Sonyflake;

test('Sonyflake upstream mode matches the canonical field layout', function (): void {
    $epoch = 1_577_836_800_000;
    $id = (string) ((1 << 24) | (1 << 16) | 42);
    expect($id)->toBe('16842794');

    $parts = Sonyflake::parseWithEpoch($id, $epoch, SonyflakeFormat::UPSTREAM);
    expect($parts['sequence'])->toBe(1)
        ->and($parts['machine_id'])->toBe(42)
        ->and($parts['time']->format('Uv'))->toBe((string) ($epoch + 10));
});

test('Sonyflake legacy mode keeps UID field ordering', function (): void {
    $epoch = 1_577_836_800_000;
    $id = (string) ((1 << 24) | (42 << 8) | 1);
    $parts = Sonyflake::parseWithEpoch($id, $epoch);

    expect($parts['sequence'])->toBe(1)
        ->and($parts['machine_id'])->toBe(42);
});
