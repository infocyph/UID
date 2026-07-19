<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use Closure;

trait ResolvesMachineId
{
    private readonly ?Closure $machineIdResolver;

    public function resolveMachineId(): int
    {
        if ($this->machineIdResolver === null) {
            return $this->machineId;
        }

        $machineId = ($this->machineIdResolver)();
        if (!is_int($machineId)) {
            throw new \UnexpectedValueException('Machine ID resolver must return an integer');
        }

        return $machineId;
    }
}
