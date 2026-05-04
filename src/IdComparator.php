<?php

declare(strict_types=1);

namespace Infocyph\UID;

use Infocyph\UID\Contracts\IdValueInterface;
use Infocyph\UID\Support\UnsignedDecimal;

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
            return UnsignedDecimal::compare($leftString, $rightString);
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
}
