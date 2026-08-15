<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use InvalidArgumentException;

final class BaseEncoder
{
    private const ALPHABETS = [
        10 => '0123456789',
        16 => '0123456789abcdef',
        32 => '0123456789abcdefghijklmnopqrstuv',
        36 => '0123456789abcdefghijklmnopqrstuvwxyz',
        58 => '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz',
        62 => '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
    ];

    private const MAX_BYTE_LENGTH = 1024;

    /**
     * Decodes one of supported bases (16/32/36/58/62) into bytes.
     */
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

        $alphabet = self::alphabet($base);
        $maxEncodedLength = (int) ceil(($bytesLength * 8) / log($base, 2));
        if (strlen($encoded) > $maxEncodedLength) {
            throw new InvalidArgumentException('Encoded value exceeds target byte length');
        }

        $bytes = [0];
        $encodedLength = strlen($encoded);

        for ($index = 0; $index < $encodedLength; ++$index) {
            $char = $encoded[$index];
            $alphabetIndex = strpos($alphabet, $char);
            $alphabetIndex !== false || throw new InvalidArgumentException('Invalid character for base ' . $base);

            $carry = $alphabetIndex;
            $byteCount = count($bytes);
            for ($byteIndex = $byteCount - 1; $byteIndex >= 0; --$byteIndex) {
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

    /**
     * Encodes bytes into one of supported bases (16/32/36/58/62).
     */
    public static function encodeBytes(string $bytes, int $base): string
    {
        $byteLength = strlen($bytes);
        if ($byteLength < 1 || $byteLength > self::MAX_BYTE_LENGTH) {
            throw new InvalidArgumentException('Byte length must be between 1 and 1024');
        }

        if ($base === 16) {
            return ltrim(bin2hex($bytes), '0') ?: '0';
        }

        $alphabet = self::alphabet($base);
        $unpacked = unpack('C*', $bytes);
        $unpacked !== false || throw new \LogicException('Unable to unpack byte value');
        $number = [];
        foreach ($unpacked as $byte) {
            is_int($byte) || throw new \LogicException('Unable to unpack byte value');
            $number[] = $byte;
        }

        if (trim($bytes, "\0") === '') {
            return $alphabet[0];
        }

        $encoded = '';
        while ($number !== []) {
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

            $encoded = $alphabet[$remainder] . $encoded;
            $number = $quotient;
        }

        return $encoded;
    }

    private static function alphabet(int $base): string
    {
        return self::ALPHABETS[$base] ?? throw new InvalidArgumentException('Unsupported base: ' . $base);
    }
}
