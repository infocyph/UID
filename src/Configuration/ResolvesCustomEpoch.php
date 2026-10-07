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

    private static function normalizeEpoch(DateTimeInterface|int|null $customEpoch): ?int
    {
        if ($customEpoch === null) {
            return null;
        }

        return $customEpoch instanceof DateTimeInterface
            ? (int) $customEpoch->format('Uv')
            : $customEpoch;
    }
}
