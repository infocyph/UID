<?php

declare(strict_types=1);

namespace Infocyph\UID\Sequence;

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
        if ($last !== null && $last['timestamp'] === $timestamp) {
            $sequence = $last['sequence'] + 1;
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
