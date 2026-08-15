<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use InvalidArgumentException;

final class TypeIdCodec
{
    private const ALPHABET = '0123456789abcdefghjkmnpqrstvwxyz';

    public static function decode(string $suffix): string
    {
        if (strlen($suffix) !== 26 || $suffix[0] > '7') {
            throw new InvalidArgumentException('TypeID suffix must be a canonical 128-bit value');
        }

        $bytes = [0];
        for ($index = 0; $index < 26; ++$index) {
            $digit = strpos(self::ALPHABET, $suffix[$index]);
            $digit !== false || throw new InvalidArgumentException('TypeID suffix contains an invalid character');
            $carry = $digit;

            for ($byteIndex = count($bytes) - 1; $byteIndex >= 0; --$byteIndex) {
                $value = ($bytes[$byteIndex] << 5) + $carry;
                $bytes[$byteIndex] = $value & 0xff;
                $carry = $value >> 8;
            }

            while ($carry > 0) {
                array_unshift($bytes, $carry & 0xff);
                $carry >>= 8;
            }
        }

        if (count($bytes) > 16) {
            throw new InvalidArgumentException('TypeID suffix exceeds 128 bits');
        }

        $decoded = '';
        foreach ($bytes as $byte) {
            $decoded .= chr($byte);
        }

        return str_repeat("\0", 16 - strlen($decoded)) . $decoded;
    }

    public static function encode(string $bytes): string
    {
        if (strlen($bytes) !== 16) {
            throw new InvalidArgumentException('TypeID encoding requires exactly 16 bytes');
        }

        $unpacked = unpack('C*', $bytes);
        $unpacked !== false || throw new \LogicException('Unable to unpack TypeID bytes');
        $number = [];
        foreach ($unpacked as $byte) {
            is_int($byte) || throw new \LogicException('Unable to unpack TypeID bytes');
            $number[] = $byte;
        }
        $encoded = '';

        while ($number !== []) {
            $quotient = [];
            $remainder = 0;
            foreach ($number as $byte) {
                $value = ($remainder << 8) | $byte;
                $digit = intdiv($value, 32);
                $remainder = $value % 32;
                if ($quotient !== [] || $digit !== 0) {
                    $quotient[] = $digit;
                }
            }

            $encoded = self::ALPHABET[$remainder] . $encoded;
            $number = $quotient;
        }

        return str_pad($encoded, 26, '0', STR_PAD_LEFT);
    }
}
