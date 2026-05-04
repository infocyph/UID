<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

final class UnsignedDecimal
{
    public static function compare(string $left, string $right): int
    {
        $left = self::normalize($left);
        $right = self::normalize($right);

        $lengthComparison = strlen($left) <=> strlen($right);
        if ($lengthComparison !== 0) {
            return $lengthComparison;
        }

        return strcmp($left, $right);
    }

    public static function normalize(string $value): string
    {
        $normalized = ltrim($value, '0');

        return $normalized === '' ? '0' : $normalized;
    }
}
