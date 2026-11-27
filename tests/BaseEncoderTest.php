<?php

declare(strict_types=1);

use Infocyph\UID\Support\BaseEncoder;

test('radix encoding matches independent integer vectors across word and carry boundaries', function (): void {
    // Generated with Python int.from_bytes/divmod, independently of the PHP codec.
    $json = file_get_contents(__DIR__ . '/Fixtures/radix-vectors.json');
    $json !== false || throw new RuntimeException('Unable to read radix vectors');
    $vectors = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    is_array($vectors) || throw new RuntimeException('Invalid radix vectors');

    foreach ($vectors as $vector) {
        if (!is_array($vector) || !is_string($vector['hex'] ?? null) || !is_int($vector['base'] ?? null) || !is_string($vector['sha256'] ?? null)) {
            throw new RuntimeException('Invalid radix vector');
        }
        $bytes = hex2bin($vector['hex']);
        $bytes !== false || throw new RuntimeException('Invalid radix vector bytes');
        $encoded = BaseEncoder::encodeBytes($bytes, $vector['base']);

        expect(hash('sha256', $encoded))->toBe($vector['sha256'])
            ->and(BaseEncoder::decodeToBytes($encoded, $vector['base'], strlen($bytes)))->toBe($bytes);
    }
});

test('radix encoding retains input bounds and supported alphabets', function (): void {
    expect(fn(): string => BaseEncoder::encodeBytes('', 36))->toThrow(InvalidArgumentException::class)
        ->and(fn(): string => BaseEncoder::encodeBytes(str_repeat("\0", 1025), 36))->toThrow(InvalidArgumentException::class)
        ->and(fn(): string => BaseEncoder::encodeBytes("\x01", 37))->toThrow(InvalidArgumentException::class);
});
