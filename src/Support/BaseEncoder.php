<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use InvalidArgumentException;

final class BaseEncoder
{
    private const array ALPHABETS = [
        10 => '0123456789',
        16 => '0123456789abcdef',
        32 => '0123456789abcdefghijklmnopqrstuv',
        36 => '0123456789abcdefghijklmnopqrstuvwxyz',
        58 => '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz',
        62 => '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
    ];

    private const int MAX_BYTE_LENGTH = 1024;

    /** @var array<int, array{int, int}> */
    private const array RADIX_GROUPS = [
        10 => [1_000_000_000, 9],
        32 => [1_073_741_824, 6],
        36 => [60_466_176, 5],
        58 => [656_356_768, 5],
        62 => [916_132_832, 5],
    ];

    public static function decodeToBytes(string $encoded, int $base, int $bytesLength): string
    {
        if ($encoded === '') {
            throw new InvalidArgumentException('Encoded value must not be empty');
        }
        if ($bytesLength < 1 || $bytesLength > self::MAX_BYTE_LENGTH) {
            throw new InvalidArgumentException('Byte length must be between 1 and 1024');
        }

        if ($base === 16) {
            if (strlen($encoded) > $bytesLength * 2 || preg_match('/^[0-9a-f]+$/D', $encoded) !== 1) {
                throw new InvalidArgumentException('Invalid character for base 16');
            }

            $decoded = hex2bin(str_pad($encoded, $bytesLength * 2, '0', STR_PAD_LEFT));
            $decoded !== false || throw new InvalidArgumentException('Unable to decode base 16 value');

            return $decoded;
        }

        return self::decodeRadix($encoded, $base, $bytesLength);
    }

    public static function encodeBytes(string $bytes, int $base): string
    {
        self::assertByteLength(strlen($bytes));

        if ($base === 16) {
            return ltrim(bin2hex($bytes), '0') ?: '0';
        }

        $alphabet = self::alphabet($base);
        [$radix, $width] = self::RADIX_GROUPS[$base];
        $padding = (4 - strlen($bytes) % 4) % 4;
        $number = BinaryUnpack::words(str_repeat("\0", $padding) . $bytes);
        $encoded = '';
        while ($number !== []) {
            $quotient = [];
            $remainder = 0;

            foreach ($number as $word) {
                // Each radix is at most 2^30, keeping the combined value below 2^62.
                $value = ($remainder << 32) | $word;
                $digit = intdiv($value, $radix);
                $remainder = $value % $radix;

                if ($quotient !== [] || $digit !== 0) {
                    $quotient[] = $digit;
                }
            }

            $chunk = self::encodeGroup($remainder, $base, $alphabet);
            $encoded = ($quotient === [] ? $chunk : str_pad($chunk, $width, $alphabet[0], STR_PAD_LEFT)) . $encoded;
            $number = $quotient;
        }

        return $encoded;
    }

    private static function alphabet(int $base): string
    {
        return self::ALPHABETS[$base] ?? throw new InvalidArgumentException('Unsupported base: ' . $base);
    }

    private static function assertByteLength(int $byteLength): void
    {
        if ($byteLength < 1 || $byteLength > self::MAX_BYTE_LENGTH) {
            throw new InvalidArgumentException('Byte length must be between 1 and 1024');
        }
    }

    private static function decodeRadix(string $encoded, int $base, int $bytesLength): string
    {
        $alphabet = self::alphabet($base);
        $maximumLength = (int) ceil(($bytesLength * 8) / log($base, 2));
        if (strlen($encoded) > $maximumLength) {
            throw new InvalidArgumentException('Encoded value exceeds target byte length');
        }

        $bytes = [0];
        $length = strlen($encoded);

        for ($index = 0; $index < $length; ++$index) {
            $digit = strpos($alphabet, $encoded[$index]);
            if ($digit === false) {
                throw new InvalidArgumentException('Invalid character for base ' . $base);
            }

            $carry = $digit;
            for ($byteIndex = count($bytes) - 1; $byteIndex >= 0; --$byteIndex) {
                $value = ($bytes[$byteIndex] * $base) + $carry;
                $bytes[$byteIndex] = $value & 0xff;
                $carry = $value >> 8;
            }

            while ($carry > 0) {
                array_unshift($bytes, $carry & 0xff);
                $carry >>= 8;
            }

            if (count($bytes) > $bytesLength) {
                throw new InvalidArgumentException('Encoded value exceeds target byte length');
            }
        }

        $decoded = '';
        foreach ($bytes as $byte) {
            $decoded .= chr($byte);
        }

        return str_repeat("\0", $bytesLength - strlen($decoded)) . $decoded;
    }

    private static function encodeGroup(int $value, int $base, string $alphabet): string
    {
        $encoded = '';
        do {
            $encoded = $alphabet[$value % $base] . $encoded;
            $value = intdiv($value, $base);
        } while ($value > 0);

        return $encoded;
    }
}
