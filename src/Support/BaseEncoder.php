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

    public static function decodeToBytes(string $encoded, int $base, int $bytesLength): string
    {
        if ($encoded === '') {
            throw new InvalidArgumentException('Encoded value must not be empty');
        }

        self::assertByteLength($bytesLength);
        if ($base === 16) {
            return self::decodeHex($encoded, $bytesLength);
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
        if (trim($bytes, "\0") === '') {
            return $alphabet[0];
        }

        return self::encodeRadix(self::unpackBytes($bytes), $base, $alphabet);
    }

    private static function alphabet(int $base): string
    {
        return self::ALPHABETS[$base] ?? throw new InvalidArgumentException('Unsupported base: ' . $base);
    }

    private static function appendDigit(array &$bytes, int $base, int $digit): void
    {
        $carry = $digit;
        for ($index = count($bytes) - 1; $index >= 0; --$index) {
            $value = (((int) $bytes[$index]) * $base) + $carry;
            $bytes[$index] = $value & 0xff;
            $carry = $value >> 8;
        }

        while ($carry > 0) {
            array_unshift($bytes, $carry & 0xff);
            $carry >>= 8;
        }
    }

    private static function assertByteLength(int $byteLength): void
    {
        if ($byteLength < 1 || $byteLength > self::MAX_BYTE_LENGTH) {
            throw new InvalidArgumentException('Byte length must be between 1 and 1024');
        }
    }

    private static function byteString(array $bytes): string
    {
        $decoded = '';
        foreach ($bytes as $byte) {
            $decoded .= chr(((int) $byte) & 0xff);
        }

        return $decoded;
    }

    private static function decodeHex(string $encoded, int $bytesLength): string
    {
        if (strlen($encoded) > $bytesLength * 2 || preg_match('/^[0-9a-f]+$/D', $encoded) !== 1) {
            throw new InvalidArgumentException('Invalid character for base 16');
        }

        $decoded = hex2bin(str_pad($encoded, $bytesLength * 2, '0', STR_PAD_LEFT));
        $decoded !== false || throw new InvalidArgumentException('Unable to decode base 16 value');

        return $decoded;
    }

    private static function decodeRadix(string $encoded, int $base, int $bytesLength): string
    {
        $alphabet = self::alphabet($base);
        $maximumLength = (int) ceil(($bytesLength * 8) / log($base, 2));
        if (strlen($encoded) > $maximumLength) {
            throw new InvalidArgumentException('Encoded value exceeds target byte length');
        }

        /** @var list<int> $bytes */
        $bytes = [0];
        $length = strlen($encoded);
        for ($index = 0; $index < $length; ++$index) {
            $digit = strpos($alphabet, $encoded[$index]);
            if ($digit === false) {
                throw new InvalidArgumentException('Invalid character for base ' . $base);
            }

            self::appendDigit($bytes, $base, $digit);
            if (count($bytes) > $bytesLength) {
                throw new InvalidArgumentException('Encoded value exceeds target byte length');
            }
        }

        $decoded = self::byteString($bytes);

        return str_repeat("\0", $bytesLength - strlen($decoded)) . $decoded;
    }

    /**
     * @param list<int> $number
     */
    private static function divide(array &$number, int $base): int
    {
        $quotient = [];
        $remainder = 0;
        foreach ($number as $byte) {
            $value = ($remainder << 8) | $byte;
            $digit = intdiv($value, $base);
            $remainder = $value % $base;
            if ($quotient !== [] || $digit !== 0) {
                $quotient[] = $digit;
            }
        }

        $number = $quotient;

        return $remainder;
    }

    /**
     * @param list<int> $number
     */
    private static function encodeRadix(array $number, int $base, string $alphabet): string
    {
        $encoded = '';
        while ($number !== []) {
            $remainder = self::divide($number, $base);
            $encoded = $alphabet[$remainder] . $encoded;
        }

        return $encoded;
    }

    /**
     * @return list<int>
     */
    private static function unpackBytes(string $bytes): array
    {
        $unpacked = unpack('C*', $bytes);
        $unpacked !== false || throw new \LogicException('Unable to unpack byte value');

        $number = [];
        foreach ($unpacked as $byte) {
            is_int($byte) || throw new \LogicException('Unable to unpack byte value');
            $number[] = $byte;
        }

        return $number;
    }
}
