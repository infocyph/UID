<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use InvalidArgumentException;
use LogicException;

final class DecimalBytes
{
    private const int MAX_BYTE_LENGTH = 1024;

    private const string MAX_SIGNED_64 = '9223372036854775807';

    private const string MAX_UNSIGNED_64 = '18446744073709551615';

    private const string MIN_SIGNED_64_MAGNITUDE = '9223372036854775808';

    private const string TWO_TO_64 = '18446744073709551616';

    public static function fromBytes(string $bytes): string
    {
        return BaseEncoder::encodeBytes($bytes, 10);
    }

    public static function fromLittleEndianSigned64(string $bytes): string
    {
        if (strlen($bytes) !== 8) {
            throw new InvalidArgumentException('Signed 64-bit value must contain exactly 8 bytes');
        }

        $unsigned = self::fromBytes(strrev($bytes));
        if ((ord($bytes[7]) & 0x80) === 0) {
            return $unsigned;
        }

        return '-' . self::subtract(self::TWO_TO_64, $unsigned);
    }

    public static function isSigned64(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if ($value[0] === '-') {
            $magnitude = substr($value, 1);

            return $magnitude !== ''
                && ctype_digit($magnitude)
                && $magnitude[0] !== '0'
                && UnsignedDecimal::compare($magnitude, self::MIN_SIGNED_64_MAGNITUDE) <= 0;
        }

        return ctype_digit($value)
            && ($value === '0' || $value[0] !== '0')
            && UnsignedDecimal::compare($value, self::MAX_SIGNED_64) <= 0;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function toFixedBytes(string $decimal, int $byteLength): string
    {
        if ($byteLength < 1 || $byteLength > self::MAX_BYTE_LENGTH) {
            throw new InvalidArgumentException('Byte length must be between 1 and 1024');
        }

        if ($decimal === '' || !ctype_digit($decimal)) {
            throw new InvalidArgumentException('Decimal value must contain only digits');
        }

        return BaseEncoder::decodeToBytes(UnsignedDecimal::normalize($decimal), 10, $byteLength);
    }

    public static function toLittleEndianSigned64(string $value): string
    {
        if (!self::isSigned64($value)) {
            throw new InvalidArgumentException('Value is outside the signed 64-bit domain');
        }

        $unsigned = $value[0] === '-'
            ? self::subtract(self::TWO_TO_64, substr($value, 1))
            : $value;

        if (UnsignedDecimal::compare($unsigned, self::MAX_UNSIGNED_64) > 0) {
            throw new InvalidArgumentException('Value is outside the unsigned 64-bit storage domain');
        }

        return strrev(self::toFixedBytes($unsigned, 8));
    }

    private static function subtract(string $left, string $right): string
    {
        if (UnsignedDecimal::compare($left, $right) < 0) {
            throw new InvalidArgumentException('Unsigned subtraction would become negative');
        }

        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $borrow = 0;
        $result = '';

        while ($leftIndex >= 0) {
            $digit = (ord($left[$leftIndex]) - 48) - $borrow;
            $rightDigit = $rightIndex >= 0 ? ord($right[$rightIndex]) - 48 : 0;
            if ($digit < $rightDigit) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $difference = $digit - $rightDigit;
            if ($difference < 0 || $difference > 9) {
                throw new LogicException('Signed decimal subtraction produced an invalid digit');
            }

            $result = $difference . $result;
            --$leftIndex;
            --$rightIndex;
        }

        return UnsignedDecimal::normalize($result);
    }
}
