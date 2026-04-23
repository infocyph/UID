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

    /**
     * Decodes one of supported bases (16/32/36/58/62) into bytes.
     */
    public static function decodeToBytes(string $encoded, int $base, int $bytesLength): string
    {
        $alphabet = self::alphabet($base);
        $decimal = '0';

        foreach (str_split($encoded) as $char) {
            $index = strpos($alphabet, $char);
            $index !== false || throw new InvalidArgumentException('Invalid character for base ' . $base);
            $decimal = bcadd(bcmul($decimal, (string) $base), (string) $index);
        }

        $hex = '';
        while ($decimal !== '0') {
            $remainder = (int) bcmod($decimal, '16');
            $hex = dechex($remainder) . $hex;
            $decimal = bcdiv($decimal, '16', 0);
        }

        $hex = str_pad($hex, $bytesLength * 2, '0', STR_PAD_LEFT);
        $bytes = hex2bin($hex);
        if ($bytes === false || strlen($bytes) !== $bytesLength) {
            throw new InvalidArgumentException('Invalid encoded value for target byte length');
        }

        return $bytes;
    }

    /**
     * Encodes bytes into one of supported bases (16/32/36/58/62).
     */
    public static function encodeBytes(string $bytes, int $base): string
    {
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
        $decimal = '0';
        foreach (str_split(bin2hex($bytes)) as $char) {
            $decimal = bcadd(
                bcmul($decimal, '16'),
                (string) hexdec($char),
            );
        }

        return $decimal;
    }
}
