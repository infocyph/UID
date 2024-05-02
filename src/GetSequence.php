<?php

namespace Infocyph\UID;

use DateTimeInterface;
use Infocyph\UID\Exceptions\FileLockException;

trait GetSequence
{
    private static string $fileLocation;

    /**
     * Generates a sequence number based on the current time.
     *
     * @param DateTimeInterface $dateTime The current time.
     * @param string $machineId The machine ID.
     * @return int The generated sequence number.
     * @throws FileLockException
     */
    private static function sequence(DateTimeInterface $dateTime, string $machineId, string $type): int
    {
        self::$fileLocation = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "uid-$type-$machineId.seq";
        if (!file_exists(self::$fileLocation)) {
            touch(self::$fileLocation);
        }
        $handle = fopen(self::$fileLocation, "r+");
        if (!flock($handle, LOCK_EX)) {
            throw new FileLockException('Could not acquire lock on ' . self::$fileLocation);
        }
        $now = $dateTime->format('Uv');
        $line = fgetcsv($handle);
        $sequence = 0;
        if ($line) {
            $sequence = match ($line[0]) {
                $now => $line[1],
                default => $sequence
            };
        }
        ftruncate($handle, 0);
        rewind($handle);
        fputcsv($handle, [$now, ++$sequence]);
        flock($handle, LOCK_UN);
        fclose($handle);
        return $sequence;
    }
}
