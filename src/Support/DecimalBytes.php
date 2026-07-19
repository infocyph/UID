<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

final class DecimalBytes
{
    private const MAX_BYTE_LENGTH = 1_048_576;

    public static function fromBytes(string $bytes): string
    {
        $decimal = '0';
        $length = strlen($bytes);
        for ($index = 0; $index < $length; ++$index) {
            $decimal = bcadd(bcmul($decimal, '256'), (string) ord($bytes[$index]));
        }

        return $decimal;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function toFixedBytes(string $decimal, int $byteLength): string
    {
        if ($byteLength < 1 || $byteLength > self::MAX_BYTE_LENGTH) {
            throw new \InvalidArgumentException('Byte length must be between 1 and 1048576');
        }

        if (preg_match('/^\d+$/', $decimal) !== 1) {
            throw new \InvalidArgumentException('Decimal value must contain only digits');
        }

        $value = UnsignedDecimal::normalize($decimal);
        $maxDecimalDigits = (int) ceil($byteLength * log10(256));
        if (strlen($value) > $maxDecimalDigits) {
            throw new \InvalidArgumentException('Decimal value exceeds target byte length');
        }

        $bytes = '';
        while ($value !== '0') {
            $remainder = (int) bcmod($value, '256');
            if ($remainder < 0 || $remainder > 255) {
                throw new \LogicException('Decimal byte remainder is outside the byte range');
            }

            $bytes = chr($remainder) . $bytes;
            $value = bcdiv($value, '256', 0);
        }

        $valueLength = strlen($bytes);
        if ($valueLength > $byteLength) {
            throw new \InvalidArgumentException('Decimal value exceeds target byte length');
        }

        return str_repeat("\0", $byteLength - $valueLength) . $bytes;
    }
}
