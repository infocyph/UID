<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use DateTimeInterface;

trait ResolvesCustomEpoch
{
    public function resolveCustomEpochMs(): ?int
    {
        return $this->customEpoch;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private static function normalizeEpoch(DateTimeInterface|int|null $customEpoch): ?int
    {
        if (!$customEpoch instanceof DateTimeInterface) {
            return $customEpoch;
        }

        $seconds = $customEpoch->getTimestamp();
        $fraction = (int) $customEpoch->format('v');
        $minimumSeconds = intdiv(PHP_INT_MIN, 1_000) - 1;
        $maximumSeconds = intdiv(PHP_INT_MAX, 1_000);
        if (
            $seconds < $minimumSeconds || $seconds > $maximumSeconds
            || ($seconds === $minimumSeconds && $fraction < 1_000 + (PHP_INT_MIN % 1_000))
            || ($seconds === $maximumSeconds && $fraction > PHP_INT_MAX % 1_000)
        ) {
            throw new \InvalidArgumentException('Custom epoch must fit in integer milliseconds');
        }

        // Add the negative fraction from the next second to avoid underflow at PHP_INT_MIN.
        return $seconds < 0
            ? (($seconds + 1) * 1_000) - (1_000 - $fraction)
            : ($seconds * 1_000) + $fraction;
    }
}
