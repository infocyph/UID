<?php

declare(strict_types=1);

namespace Infocyph\UID\Sequence;

interface SequenceProviderInterface
{
    /**
     * Returns the next sequence for a given type/machine/timestamp key.
     */
    public function next(string $type, int $machineId, int $timestamp): int;
}
