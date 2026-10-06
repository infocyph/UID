<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use InvalidArgumentException;
use LogicException;

final class SignedDecimal64
{
    private const string MAX_SIGNED = '9223372036854775807';

    private const string MAX_UNSIGNED = '18446744073709551615';

    private const string MIN_MAGNITUDE = '9223372036854775808';

    private const string TWO_TO_64 = '18446744073709551616';

    public static function fromLittleEndianBytes(string $bytes): string
    {
        if (strlen($bytes) !== 8) {
            throw new InvalidArgumentException('Signed 64-bit value must contain exactly 8 bytes');
        }

        $unsigned = DecimalBytes::fromBytes(strrev($bytes));
        if ((ord($bytes[7]) & 0x80) === 0) {
            return $unsigned;
        }

        return '-' . self::subtract(self::TWO_TO_64, $unsigned);
    }

    public static function isValid(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if ($value[0] === '-') {
            $magnitude = substr($value, 1);

            return $magnitude !== ''
                && ctype_digit($magnitude)
                && $magnitude[0] !== '0'
                && UnsignedDecimal::compare($magnitude, self::MIN_MAGNITUDE) <= 0;
        }

        return ctype_digit($value)
            && ($value === '0' || $value[0] !== '0')
            && UnsignedDecimal::compare($value, self::MAX_SIGNED) <= 0;
    }

    public static function toLittleEndianBytes(string $value): string
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException('Value is outside the signed 64-bit domain');
        }

        $unsigned = $value[0] === '-'
            ? self::subtract(self::TWO_TO_64, substr($value, 1))
            : $value;

        if (UnsignedDecimal::compare($unsigned, self::MAX_UNSIGNED) > 0) {
            throw new InvalidArgumentException('Value is outside the unsigned 64-bit storage domain');
        }

        return strrev(DecimalBytes::toFixedBytes($unsigned, 8));
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
