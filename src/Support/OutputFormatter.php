<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Exceptions\UIDException;

final class OutputFormatter
{
    /**
     * Formats an unsigned numeric string according to desired output type.
     *
     * @throws UIDException
     */
    public static function formatNumeric(int|string $value, IdOutputType $type): int|string
    {
        $decimal = (string) $value;

        return match ($type) {
            IdOutputType::STRING => $decimal,
            IdOutputType::INT => self::toInt($decimal),
            IdOutputType::BINARY => self::toBinary64($decimal),
        };
    }

    private static function compareUnsignedDecimals(string $left, string $right): int
    {
        $left = ltrim($left, '0');
        $right = ltrim($right, '0');
        $left = $left === '' ? '0' : $left;
        $right = $right === '' ? '0' : $right;

        $lengthComparison = strlen($left) <=> strlen($right);
        if ($lengthComparison !== 0) {
            return $lengthComparison;
        }

        return strcmp($left, $right);
    }

    /**
     * @throws UIDException
     */
    private static function toBinary64(string $decimal): string
    {
        $hex = '';
        $value = $decimal;

        while ($value !== '0') {
            $remainder = (int) bcmod($value, '16');
            $hex = dechex($remainder) . $hex;
            $value = bcdiv($value, '16', 0);
        }

        $hex = str_pad($hex, 16, '0', STR_PAD_LEFT);
        $binary = hex2bin($hex);
        $binary !== false || throw new UIDException('Unable to convert numeric ID to binary');

        return $binary;
    }

    /**
     * @throws UIDException
     */
    private static function toInt(string $decimal): int
    {
        if (self::compareUnsignedDecimals($decimal, (string) PHP_INT_MAX) === 1) {
            throw new UIDException('Numeric ID exceeds PHP_INT_MAX; use string or binary output');
        }

        return (int) $decimal;
    }
}
