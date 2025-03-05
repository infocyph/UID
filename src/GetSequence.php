<?php

namespace Infocyph\UID;

use RuntimeException;

trait GetSequence
{
    private static string $fileLocation;
    private static int $waitTime = 100;
    private static int $lockTimeout = 1000;
    private static int $maxAttempts = 10;

    /**
     * Generates a sequence number based on the current time.
     *
     * @param int $dateTime The current time.
     * @param int $machineId The machine ID.
     * @param string $type The type identifier.
     * @param int $maxSequenceLength The maximum length of the sequence number.
     * @return int The generated sequence number, or `0` if lock was not acquired.
     */
    private static function sequence(int $dateTime, int $machineId, string $type, int $maxSequenceLength = 0): int
    {
        self::$fileLocation ??= sys_get_temp_dir() . DIRECTORY_SEPARATOR . "uid-$type-$machineId.seq";

        // Attempt to acquire a lock
        $handle = self::acquireLock();
        if (!$handle) {
            return getmypid() % ((-1 ^ (-1 << $maxSequenceLength)) + 1);
        }

        // Update sequence
        $sequence = self::updateSequence($handle, $dateTime);

        // Unlock and close
        flock($handle, LOCK_UN);
        fclose($handle);

        return $sequence;
    }

    /**
     * Acquires an exclusive lock on the sequence file.
     * If a stale lock is detected, it resets the lock.
     *
     * @return resource|false The file handle if successful, or false if unable to acquire the lock.
     */
    private static function acquireLock()
    {
        ($handle = fopen(self::$fileLocation, "c+")) || throw new RuntimeException(
            'Failed to open file: ' . self::$fileLocation,
        );

        $lockStartTime = microtime(true);
        $attempts = 0;

        // Attempt to acquire lock with retry mechanism
        while (!flock($handle, LOCK_EX | LOCK_NB)) {
            usleep(self::$waitTime);

            // If lock is held too long, assume stale lock and reset
            if ((microtime(true) - $lockStartTime) * 1_000_000 >= self::$lockTimeout) {
                fclose($handle);
                ($handle = fopen(self::$fileLocation, "c+")) || throw new RuntimeException(
                    'Failed to reopen file after stale lock reset: ' . self::$fileLocation,
                );

                // Acquire exclusive lock after reopening
                flock($handle, LOCK_EX);
                return $handle;
            }

            // If max attempts are reached, return false
            if (++$attempts >= self::$maxAttempts) {
                fclose($handle);
                return false;
            }
        }

        return $handle;
    }

    /**
     * Reads the current sequence from the file, increments it, and writes it back.
     *
     * @param resource $handle The file handle.
     * @param int $dateTime The timestamp for sequence tracking.
     * @return int The updated sequence number.
     */
    private static function updateSequence($handle, int $dateTime): int
    {
        $sequence = 0;
        $line = stream_get_contents($handle);

        if ($line !== false && trim($line) !== '') {
            [$lastTimestamp, $lastSequence] = explode(',', trim($line));
            $lastTimestamp = (int) $lastTimestamp;

            if ($lastTimestamp === $dateTime) {
                $sequence = (int) $lastSequence;
            }
        }

        // Increment sequence
        $sequence++;

        // Move pointer to the beginning and write updated values
        rewind($handle);
        fwrite($handle, "$dateTime,$sequence");
        ftruncate($handle, ftell($handle));

        return $sequence;
    }
}
