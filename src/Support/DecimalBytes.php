<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

final class DecimalBytes
{
    public static function fromBytes(string $bytes): string
    {
        $decimal = '0';
        foreach (str_split(bin2hex($bytes)) as $char) {
            $decimal = bcadd(bcmul($decimal, '16'), (string) hexdec($char));
        }

        return $decimal;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function toFixedBytes(string $decimal, int $byteLength): string
    {
        if ($byteLength < 1) {
            throw new \InvalidArgumentException('Byte length must be greater than zero');
        }

        if (preg_match('/^\d+$/', $decimal) !== 1) {
            throw new \InvalidArgumentException('Decimal value must contain only digits');
        }

        $hex = '';
        $value = UnsignedDecimal::normalize($decimal);
        while ($value !== '0') {
            $remainder = (int) bcmod($value, '16');
            $hex = dechex($remainder) . $hex;
            $value = bcdiv($value, '16', 0);
        }

        if (strlen($hex) > ($byteLength * 2)) {
            throw new \InvalidArgumentException('Decimal value exceeds target byte length');
        }

        $hex = str_pad($hex, $byteLength * 2, '0', STR_PAD_LEFT);
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            throw new \InvalidArgumentException('Unable to convert decimal value to bytes');
        }

        return $bytes;
    }
}
