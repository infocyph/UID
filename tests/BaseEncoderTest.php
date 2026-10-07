<?php

declare(strict_types=1);

use Infocyph\UID\Support\BaseEncoder;

test('radix encoding matches independent integer vectors across word and carry boundaries', function (): void {
    // Generated with Python int.from_bytes/divmod, independently of the PHP codec.
    $vectors = json_decode(file_get_contents(__DIR__ . '/Fixtures/radix-vectors.json'), true, 512, JSON_THROW_ON_ERROR);

    foreach ($vectors as $vector) {
        $bytes = hex2bin($vector['hex']);
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
