<?php

declare(strict_types=1);

namespace Infocyph\UID\Sequence;

use Closure;
use Infocyph\UID\Exceptions\FileLockException;

final readonly class CallbackSequenceProvider implements SequenceProviderInterface
{
    private Closure $callback;

    /**
     * @param callable(string, int, int):int $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback(...);
    }

    /**
     * @throws FileLockException
     */
    public function next(string $type, int $machineId, int $timestamp): int
    {
        $value = ($this->callback)($type, $machineId, $timestamp);
        if ($value < 0) {
            throw new FileLockException('Custom sequence callback must return a non-negative integer');
        }

        return $value;
    }
}
