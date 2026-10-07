<?php

declare(strict_types=1);

use Infocyph\UID\IdComparator;
use Infocyph\UID\NanoID;
use Infocyph\UID\OpaqueId;
use Infocyph\UID\Snowflake;
use Infocyph\UID\Sonyflake;
use Infocyph\UID\TBSL;
use Infocyph\UID\ULID;
use Infocyph\UID\UUID;
use Infocyph\UID\Support\BaseEncoder;

test('text validators reject trailing newlines', function (): void {
    expect(ULID::isValid(str_repeat('0', 26) . "\n"))->toBeFalse()
        ->and(NanoID::isValid("abc\n"))->toBeFalse()
        ->and(TBSL::isValid(str_repeat('0', 20) . "\n"))->toBeFalse();
});

test('mixed comparator order is transitive', function (): void {
    expect(IdComparator::sort(['1a', '10', '2']))->toBe(['2', '10', '1a'])
        ->and(IdComparator::compare('2', '10'))->toBeLessThan(0)
        ->and(IdComparator::compare('10', '1a'))->toBeLessThan(0)
        ->and(IdComparator::compare('2', '1a'))->toBeLessThan(0);
});

test('guid fallback braces normalize cleanly', function (): void {
    $guid = UUID::guid(false);
    expect($guid)->toMatch('/^\{[0-9a-f-]{36}\}$/i')
        ->and(UUID::isValid(trim($guid, '{}')))->toBeTrue()
        ->and(UUID::fromBytes(UUID::toBytes($guid)))->toBe(strtolower(trim($guid, '{}')));
});

test('signed numeric decoders reject the unsigned 64-bit maximum', function (): void {
    $bytes = str_repeat("\xff", 8);
    expect(fn(): string => Snowflake::fromBytes($bytes))
        ->toThrow(\Infocyph\UID\Exceptions\SnowflakeException::class)
        ->and(fn(): string => Sonyflake::fromBytes($bytes))
        ->toThrow(\Infocyph\UID\Exceptions\SonyflakeException::class);
});

test('opaque ids reject decoded values outside the generation domain', function (): void {
    $token = BaseEncoder::encodeBytes(str_repeat("\xff", 8), 62);
    expect(fn(): int => OpaqueId::toInt($token))
        ->toThrow(\InvalidArgumentException::class);
});
