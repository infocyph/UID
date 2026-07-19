<?php

declare(strict_types=1);

namespace Infocyph\UID\Sequence;

use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SequenceTimestampException;
use Infocyph\UID\Support\FileLock;
use InvalidArgumentException;

final readonly class FilesystemSequenceProvider implements SequenceProviderInterface
{
    private string $baseDirectory;

    public function __construct(
        ?string $baseDirectory = null,
        private int $waitTime = 1_000,
        private int $maxAttempts = 1_000,
    ) {
        $this->baseDirectory = $baseDirectory ?: sys_get_temp_dir();
    }

    /**
     * @throws FileLockException
     */
    public function next(string $type, int $machineId, int $timestamp): int
    {
        $fileLocation = $this->sequenceFileLocation($type, $machineId);
        $handle = $this->acquireLock($fileLocation);

        try {
            return $this->updateSequence($handle, $timestamp);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return resource
     * @throws FileLockException
     */
    private function acquireLock(string $fileLocation)
    {
        return FileLock::acquire(
            $fileLocation,
            $this->waitTime,
            $this->maxAttempts,
            'Failed to open sequence file: ' . $fileLocation,
            'Unable to acquire sequence lock: ' . $fileLocation,
        );
    }

    private function sequenceFileLocation(string $type, int $machineId): string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/D', $type) !== 1) {
            throw new InvalidArgumentException('Sequence type may contain only letters, numbers, underscores, and hyphens');
        }

        return $this->baseDirectory . DIRECTORY_SEPARATOR . "uid-$type-$machineId.seq";
    }

    /**
     * @param resource $handle
     * @throws FileLockException
     */
    private function updateSequence($handle, int $timestamp): int
    {
        $sequence = 0;
        $line = stream_get_contents($handle);
        if ($line === false) {
            throw new FileLockException('Unable to read sequence state');
        }

        $line = trim($line);
        if ($line !== '') {
            if (preg_match('/^(0|[1-9]\d*),(0|[1-9]\d*)$/D', $line) !== 1) {
                throw new FileLockException('Sequence state is malformed');
            }

            $parts = explode(',', $line, 2);
            $lastTimestamp = filter_var($parts[0], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $lastSequence = filter_var($parts[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($lastTimestamp === false || $lastSequence === false) {
                throw new FileLockException('Sequence state is malformed');
            }

            if ($lastTimestamp > $timestamp) {
                throw new SequenceTimestampException($lastTimestamp, $timestamp);
            }

            $sequence = $lastTimestamp === $timestamp ? $lastSequence : 0;
        }

        if ($sequence === PHP_INT_MAX) {
            throw new FileLockException('Sequence value exhausted');
        }

        ++$sequence;
        $state = "$timestamp,$sequence";

        rewind($handle) || throw new FileLockException('Unable to rewind sequence file');
        $written = fwrite($handle, $state);
        if ($written === false || $written !== strlen($state)) {
            throw new FileLockException('Unable to write complete sequence state');
        }

        ftruncate($handle, $written) || throw new FileLockException('Unable to truncate sequence file');
        fflush($handle) || throw new FileLockException('Unable to flush sequence state');

        return $sequence;
    }
}
