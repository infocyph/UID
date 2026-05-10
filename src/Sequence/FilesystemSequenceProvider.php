<?php

declare(strict_types=1);

namespace Infocyph\UID\Sequence;

use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Support\FileLock;

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

        if ($line !== false && trim($line) !== '') {
            [$lastTimestamp, $lastSequence] = explode(',', trim($line));
            $lastTimestamp = (int) $lastTimestamp;

            if ($lastTimestamp === $timestamp) {
                $sequence = (int) $lastSequence;
            }
        }

        $sequence++;

        rewind($handle);
        fwrite($handle, "$timestamp,$sequence");
        $position = ftell($handle);
        ($position !== false && $position >= 0) || throw new FileLockException(
            'Unable to determine sequence file write position',
        );
        ftruncate($handle, $position);

        return $sequence;
    }
}
