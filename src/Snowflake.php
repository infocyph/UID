<?php

namespace Infocyph\UID;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Infocyph\UID\Exceptions\SnowflakeException;

class Snowflake
{
    private static int $maxTimestampLength = 41;
    private static int $maxDatacenterLength = 5;
    private static int $maxWorkIdLength = 5;
    private static int $maxSequenceLength = 12;
    private static ?int $startTime;
    private static string $fileLocation;

    /**
     * Generates a unique snowflake ID.
     *
     * @param int $datacenter The ID of the datacenter (default: 0)
     * @param int $workerId The ID of the worker (default: 0)
     * @return string The generated snowflake ID
     * @throws SnowflakeException
     */
    public static function generate(int $datacenter = 0, int $workerId = 0): string
    {
        $maxDataCenter = -1 ^ (-1 << self::$maxDatacenterLength);
        $maxWorkId = -1 ^ (-1 << self::$maxWorkIdLength);

        if ($datacenter > $maxDataCenter || $datacenter < 0) {
            throw new SnowflakeException("Invalid datacenter ID, must be between 0 ~ $maxDataCenter.");
        }

        if ($workerId > $maxWorkId || $workerId < 0) {
            throw new SnowflakeException("Invalid worker ID, must be between 0 ~ $maxWorkId.");
        }

        $now = new DateTimeImmutable('now');
        $currentTime = (int)$now->format('Uv');
        while (($sequence = self::sequence(
                $now,
                $datacenter,
                $workerId
            )) > (-1 ^ (-1 << self::$maxSequenceLength))) {
            ++$currentTime;
        }

        $workerLeftMoveLength = self::$maxSequenceLength;
        $datacenterLeftMoveLength = self::$maxWorkIdLength + $workerLeftMoveLength;
        $timestampLeftMoveLength = self::$maxDatacenterLength + $datacenterLeftMoveLength;

        return (string)((($currentTime - self::getStartTimeStamp()) << $timestampLeftMoveLength)
            | ($datacenter << $datacenterLeftMoveLength)
            | ($workerId << $workerLeftMoveLength)
            | ($sequence));
    }

    /**
     * Parse the given ID into components.
     *
     * @param string $id The ID to parse.
     * @return array
     * @throws Exception
     */
    public static function parse(string $id): array
    {
        $id = decbin((int)$id);
        $time = str_split(bindec(substr($id, 0, -22)) + self::getStartTimeStamp(), 10);

        return [
            'time' => new DateTimeImmutable(
                '@'
                . $time[0]
                . '.'
                . str_pad($time[1], 6, '0', STR_PAD_LEFT)
            ),
            'sequence' => bindec(substr($id, -12)),
            'worker_id' => bindec(substr($id, -17, 5)),
            'datacenter_id' => bindec(substr($id, -22, 5)),
        ];
    }

    /**
     * Sets the start timestamp for the Snowflake algorithm.
     *
     * @param string $timeString The start time in string format.
     * @throws SnowflakeException
     */
    public static function setStartTimeStamp(string $timeString): void
    {
        $time = strtotime($timeString);
        $current = time();

        if ($time > $current) {
            throw new SnowflakeException('The start time cannot be in the future');
        }

        if (($current - $time) > (-1 ^ (-1 << self::$maxTimestampLength))) {
            throw new SnowflakeException(
                sprintf(
                    'The current microtime - start_time is not allowed to exceed -1 ^ (-1 << %d),
                    You can reset the start time to fix this',
                    self::$maxTimestampLength
                )
            );
        }

        self::$startTime = $time * 1000;
    }

    /**
     * Retrieves the start timestamp.
     *
     * @return float|int The start timestamp in milliseconds.
     */
    private static function getStartTimeStamp(): float|int
    {
        return self::$startTime ??= (strtotime('2020-01-01 00:00:00') * 1000);
    }

    /**
     * Generates a sequence number based on the current time.
     *
     * @param DateTimeInterface $now The current time.
     * @param int $datacenter
     * @param int $workerId
     * @return int The generated sequence number.
     * @throws SnowflakeException
     */
    private static function sequence(DateTimeInterface $now, int $datacenter, int $workerId): int
    {
        self::$fileLocation = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            'uid-snf-' . $datacenter . $workerId . $now->format('Ymd') . '.seq';
        if (!file_exists(self::$fileLocation)) {
            touch(self::$fileLocation);
        }
        $handle = fopen(self::$fileLocation, "r+");
        if (!flock($handle, LOCK_EX)) {
            throw new SnowflakeException('Could not acquire lock on ' . self::$fileLocation);
        }
        $content = '';
        while (!feof($handle)) {
            $content .= fread($handle, 1024);
        }
        $content = json_decode($content, true);
        $currentTime = (int)$now->format('Uv');
        $content[$currentTime] = ($content[$currentTime] ?? 0) + 1;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($content));
        flock($handle, LOCK_UN);
        fclose($handle);

        return $content[$currentTime];
    }
}
