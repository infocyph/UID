<?php

declare(strict_types=1);

namespace Infocyph\UID;

use Infocyph\UID\Contracts\IdValueInterface;

final class IdComparator
{
    /**
     * Compares IDs lexically or numerically when both are digit-only.
     */
    public static function compare(IdValueInterface|string $left, IdValueInterface|string $right): int
    {
        $leftString = $left instanceof IdValueInterface ? $left->toString() : $left;
        $rightString = $right instanceof IdValueInterface ? $right->toString() : $right;

        if (preg_match('/^\d+$/', $leftString) && preg_match('/^\d+$/', $rightString)) {
            return self::compareUnsignedDecimals($leftString, $rightString);
        }

        return strcmp($leftString, $rightString);
    }

    /**
     * Sorts IDs in ascending order.
     *
     * @param array<int, IdValueInterface|string> $ids
     * @return array<int, IdValueInterface|string>
     */
    public static function sort(array $ids): array
    {
        usort($ids, self::compare(...));

        return $ids;
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
}
