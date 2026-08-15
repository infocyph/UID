<?php

declare(strict_types=1);

namespace Infocyph\UID\Sequence;

use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SequenceTimestampException;
use Infocyph\UID\Support\FileLock;
use InvalidArgumentException;

final class FilesystemSequenceProvider implements SequenceProviderInterface
{
    private const MAX_PATH_CACHE = 1024;

    private const MAX_SEQUENCE_STATE_BYTES = 64;

    private readonly string $baseDirectory;

    /** @var array<string, string> */
    private array $pathCache = [];

    /** @var array<string, array{timestamp:int,next:int,end:int}> */
    private array $reservations = [];

    private ?int $sourcePid = null;

    public function __construct(
        ?string $baseDirectory = null,
        private readonly string $namespace = '',
        private readonly ?int $lockTimeoutMicros = null,
        private readonly int $reservationSize = 1,
    ) {
        $this->baseDirectory = $baseDirectory ?: sys_get_temp_dir();

        if ($namespace !== '' && preg_match('/^[A-Za-z0-9_-]+$/D', $namespace) !== 1) {
            throw new InvalidArgumentException('Sequence namespace may contain only letters, numbers, underscores, and hyphens');
        }

        if ($lockTimeoutMicros !== null && $lockTimeoutMicros < 0) {
            throw new InvalidArgumentException('Lock timeout must not be negative');
        }

        if ($reservationSize < 1) {
            throw new InvalidArgumentException('Reservation size must be a positive integer');
        }
    }

    public function next(string $type, int $machineId, int $timestamp): int
    {
        $fileLocation = $this->sequenceFileLocation($type, $machineId);
        $this->resetAfterFork();
        $reservation = $this->reservations[$fileLocation] ?? null;

        if (
            $reservation !== null
            && $reservation['timestamp'] === $timestamp
            && $reservation['next'] <= $reservation['end']
        ) {
            $allocation = $reservation['next'];
            $this->reservations[$fileLocation]['next'] = $allocation + 1;

            return $allocation;
        }

        $handle = FileLock::acquire(
            $fileLocation,
            $this->lockTimeoutMicros,
            'Failed to open sequence file: ' . $fileLocation,
            'Unable to acquire sequence lock: ' . $fileLocation,
        );

        try {
            [$lastTimestamp, $lastAllocation, $oldLength] = $this->readState($handle);
            if ($lastTimestamp > $timestamp) {
                throw new SequenceTimestampException($lastTimestamp, $timestamp);
            }

            $allocation = $lastTimestamp === $timestamp ? $lastAllocation + 1 : 1;
            if ($allocation > PHP_INT_MAX - $this->reservationSize + 1) {
                throw new FileLockException('Sequence value exhausted');
            }

            $reservedEnd = $allocation + $this->reservationSize - 1;
            $state = $timestamp . ',' . $reservedEnd;
            $this->writeState($handle, $state, $oldLength);
            $this->reservations[$fileLocation] = [
                'timestamp' => $timestamp,
                'next' => $allocation + 1,
                'end' => $reservedEnd,
            ];

            return $allocation;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function isCanonicalInteger(string $value): bool
    {
        return $value !== ''
            && ctype_digit($value)
            && ($value === '0' || $value[0] !== '0');
    }

    /**
     * @param resource $handle
     * @return array{0:int,1:int,2:int}
     */
    private function readState($handle): array
    {
        $state = stream_get_contents($handle, self::MAX_SEQUENCE_STATE_BYTES + 1);
        if ($state === false) {
            throw new FileLockException('Unable to read sequence state');
        }

        $oldLength = strlen($state);
        if ($oldLength > self::MAX_SEQUENCE_STATE_BYTES) {
            throw new FileLockException('Sequence state exceeds the maximum size');
        }

        if ($state === '') {
            return [0, 0, 0];
        }

        $comma = strpos($state, ',');
        if ($comma === false || str_contains(substr($state, $comma + 1), ',')) {
            throw new FileLockException('Sequence state is malformed');
        }

        $timestamp = substr($state, 0, $comma);
        $allocation = substr($state, $comma + 1);
        if (!self::isCanonicalInteger($timestamp) || !self::isCanonicalInteger($allocation)) {
            throw new FileLockException('Sequence state is malformed');
        }

        if (
            strlen($timestamp) > 19
            || strlen($allocation) > 19
            || (strlen($timestamp) === 19 && $timestamp > (string) PHP_INT_MAX)
            || (strlen($allocation) === 19 && $allocation > (string) PHP_INT_MAX)
        ) {
            throw new FileLockException('Sequence state is malformed');
        }

        return [(int) $timestamp, (int) $allocation, $oldLength];
    }

    private function resetAfterFork(): void
    {
        $pid = (int) getmypid();
        if ($pid === $this->sourcePid) {
            return;
        }

        $this->sourcePid = $pid;
        $this->reservations = [];
    }

    private function sequenceFileLocation(string $type, int $machineId): string
    {
        $cacheKey = $type . ':' . $machineId;
        if (isset($this->pathCache[$cacheKey])) {
            return $this->pathCache[$cacheKey];
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/D', $type) !== 1) {
            throw new InvalidArgumentException('Sequence type may contain only letters, numbers, underscores, and hyphens');
        }

        $name = 'uid-' . ($this->namespace === '' ? '' : $this->namespace . '-') . $type . '-' . $machineId . '.seq';
        if (count($this->pathCache) === self::MAX_PATH_CACHE) {
            array_shift($this->pathCache);
        }

        return $this->pathCache[$cacheKey] = $this->baseDirectory . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * @param resource $handle
     */
    private function writeState($handle, string $state, int $oldLength): void
    {
        rewind($handle) || throw new FileLockException('Unable to rewind sequence file');
        $written = fwrite($handle, $state);
        if ($written === false || $written !== strlen($state)) {
            throw new FileLockException('Unable to write complete sequence state');
        }

        if ($written < $oldLength) {
            ftruncate($handle, $written) || throw new FileLockException('Unable to truncate sequence file');
        }

        fflush($handle) || throw new FileLockException('Unable to flush sequence state');
    }
}
