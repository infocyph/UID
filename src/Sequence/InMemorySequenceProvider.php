<?php

declare(strict_types=1);

namespace Infocyph\UID\Sequence;

use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SequenceTimestampException;

final class InMemorySequenceProvider implements SequenceProviderInterface
{
    /**
     * @var array<string, array{timestamp:int, sequence:int}>
     */
    private array $state = [];

    public function next(string $type, int $machineId, int $timestamp): int
    {
        $key = $this->key($type, $machineId);
        $last = $this->state[$key] ?? null;

        $sequence = 1;
        if ($last !== null) {
            if ($last['timestamp'] > $timestamp) {
                throw new SequenceTimestampException($last['timestamp'], $timestamp);
            }

            if ($last['timestamp'] === $timestamp) {
                if ($last['sequence'] === PHP_INT_MAX) {
                    throw new FileLockException('Sequence value exhausted');
                }

                $sequence = $last['sequence'] + 1;
            }
        }

        $this->state[$key] = [
            'timestamp' => $timestamp,
            'sequence' => $sequence,
        ];

        return $sequence;
    }

    private function key(string $type, int $machineId): string
    {
        return $type . ':' . $machineId;
    }
}
