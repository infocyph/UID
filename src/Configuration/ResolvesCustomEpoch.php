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

    private static function resolveEpochValue(DateTimeInterface|int|string|null $customEpoch): ?int
    {
        if ($customEpoch === null) {
            return null;
        }

        if ($customEpoch instanceof DateTimeInterface) {
            return (int) $customEpoch->format('Uv');
        }

        if (is_int($customEpoch)) {
            return $customEpoch;
        }

        $epoch = strtotime($customEpoch);

        return $epoch === false ? null : $epoch * 1000;
    }
}
