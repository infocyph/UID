<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use InvalidArgumentException;

final class BaseEncoder
{
    private const ALPHABETS = [
        16 => '0123456789abcdef',
        32 => '0123456789abcdefghijklmnopqrstuv',
        36 => '0123456789abcdefghijklmnopqrstuvwxyz',
        58 => '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz',
        62 => '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
    ];

    private const MAX_BYTE_LENGTH = 1_048_576;

    /**
     * Decodes one of supported bases (16/32/36/58/62) into bytes.
     */
    public static function decodeToBytes(string $encoded, int $base, int $bytesLength): string
    {
        if ($encoded === '') {
            throw new InvalidArgumentException('Encoded value must not be empty');
        }

        if ($bytesLength < 1 || $bytesLength > self::MAX_BYTE_LENGTH) {
            throw new InvalidArgumentException('Byte length must be between 1 and 1048576');
        }

        $alphabet = self::alphabet($base);
        $maxEncodedLength = (int) ceil(($bytesLength * 8) / log($base, 2));
        if (strlen($encoded) > $maxEncodedLength) {
            throw new InvalidArgumentException('Encoded value exceeds target byte length');
        }

        $decimal = '0';
        $encodedLength = strlen($encoded);

        for ($index = 0; $index < $encodedLength; ++$index) {
            $char = $encoded[$index];
            $alphabetIndex = strpos($alphabet, $char);
            $alphabetIndex !== false || throw new InvalidArgumentException('Invalid character for base ' . $base);
            $decimal = bcadd(bcmul($decimal, (string) $base), (string) $alphabetIndex);
        }

        return DecimalBytes::toFixedBytes($decimal, $bytesLength);
    }

    /**
     * Encodes bytes into one of supported bases (16/32/36/58/62).
     */
    public static function encodeBytes(string $bytes, int $base): string
    {
        $byteLength = strlen($bytes);
        if ($byteLength < 1 || $byteLength > self::MAX_BYTE_LENGTH) {
            throw new InvalidArgumentException('Byte length must be between 1 and 1048576');
        }

        $alphabet = self::alphabet($base);
        $decimal = self::bytesToDecimal($bytes);

        if ($decimal === '0') {
            return '0';
        }

        $encoded = '';
        while ($decimal !== '0') {
            $remainder = (int) bcmod($decimal, (string) $base);
            $encoded = $alphabet[$remainder] . $encoded;
            $decimal = bcdiv($decimal, (string) $base, 0);
        }

        return $encoded;
    }

    private static function alphabet(int $base): string
    {
        return self::ALPHABETS[$base] ?? throw new InvalidArgumentException('Unsupported base: ' . $base);
    }

    private static function bytesToDecimal(string $bytes): string
    {
        return DecimalBytes::fromBytes($bytes);
    }
}
