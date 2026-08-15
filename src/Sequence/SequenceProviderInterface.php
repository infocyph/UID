<?php

declare(strict_types=1);

namespace Infocyph\UID\Sequence;

interface SequenceProviderInterface
{
    /**
     * Returns the next positive allocation, beginning at one, for a key.
     */
    public function next(string $type, int $machineId, int $timestamp): int;
}
