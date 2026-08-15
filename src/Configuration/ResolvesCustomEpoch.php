<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use DateTimeInterface;

trait ResolvesCustomEpoch
{
    public function resolveCustomEpochMs(): ?int
    {
        return self::resolveEpochValue($this->customEpoch);
    }

    private static function resolveEpochValue(DateTimeInterface|int|null $customEpoch): ?int
    {
        if ($customEpoch === null) {
            return null;
        }

        if ($customEpoch instanceof DateTimeInterface) {
            return (int) $customEpoch->format('Uv');
        }

        return $customEpoch;
    }
}
