<?php

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

class Snowflake
{
    private static int $maxTimestampLength = 41;
    private static int $maxDatacenterLength = 5;
    private static int $maxWorkIdLength = 5;
    private static int $maxSequenceLength = 12;
    private static int $lastTimeStamp = 0;
    private static int $sequence;
    private static int $dataCenter;
    private static int $workerId;
    private static ?int $startTime;
    private static int $maxSequence;

    /**
     * Generates a unique identifier using the Snowflake algorithm.
     *
     * @param int $datacenter The datacenter ID (default: 0)
     * @param int $workerId The worker ID (default: 0)
     * @return string The generated unique identifier
     * @throws Exception
     */
    public static function generate(int $datacenter = 0, int $workerId = 0): string
    {
        $maxDataCenter = -1 ^ (-1 << self::$maxDatacenterLength);
        $maxWorkId = -1 ^ (-1 << self::$maxWorkIdLength);

        self::$dataCenter = $datacenter > $maxDataCenter || $datacenter < 0 ? random_int(0, 31) : $datacenter;
        self::$workerId = $workerId > $maxWorkId || $workerId < 0 ? random_int(0, 31) : $workerId;

        $currentTime = (int)(new DateTimeImmutable('now'))->format('Uv');
        while (($sequence = self::sequence($currentTime)) > (-1 ^ (-1 << self::$maxSequenceLength))) {
            $currentTime++;
        }

        $workerLeftMoveLength = self::$maxSequenceLength;
        $datacenterLeftMoveLength = self::$maxWorkIdLength + $workerLeftMoveLength;
        $timestampLeftMoveLength = self::$maxDatacenterLength + $datacenterLeftMoveLength;

        return (string)((($currentTime - self::getStartTimeStamp()) << $timestampLeftMoveLength)
            | (self::$dataCenter << $datacenterLeftMoveLength)
            | (self::$workerId << $workerLeftMoveLength)
            | ($sequence));
    }

    /**
     * Parse the given ID into components.
     *
     * @param string $id The ID to parse.
     * @return array
     */
    public static function parse(string $id): array
    {
        $id = decbin((int)$id);

        return [
            'timestamp' => bindec(substr($id, 0, -22)),
            'sequence' => bindec(substr($id, -12)),
            'worker_id' => bindec(substr($id, -17, 5)),
            'data_center_id' => bindec(substr($id, -22, 5)),
        ];
    }

    /**
     * Sets the start timestamp for the Snowflake algorithm.
     *
     * @param string $timeString The start time in string format.
     * @throws InvalidArgumentException
     */
    public static function setStartTimeStamp(string $timeString): void
    {
        $time = strtotime($timeString);
        $current = time();

        if ($time > $current) {
            throw new InvalidArgumentException('The start time cannot be in the future');
        }

        if (($current - $time) > (-1 ^ (-1 << self::$maxTimestampLength))) {
            throw new InvalidArgumentException(
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
     * Sets the maximum sequence value.
     *
     * @param int $maxSequence The maximum sequence value to set.
     * @return void
     */
    public static function setMaxSequence(int $maxSequence): void
    {
        self::$maxSequence = $maxSequence;
    }

    /**
     * Retrieves the start timestamp for the Snowflake algorithm.
     *
     * @return float|int The start timestamp in milliseconds.
     */
    private static function getStartTimeStamp(): float|int
    {
        if (!empty(self::$startTime)) {
            return self::$startTime;
        }

        // default start time, if not set.
        $defaultTime = '2000-01-01 00:00:00';
        return strtotime($defaultTime) * 1000;
    }

    /**
     * Generates a sequence number based on the current time.
     *
     * @param int $currentTime The current time in milliseconds.
     * @return int The generated sequence number.
     * @throws Exception
     */
    private static function sequence(int $currentTime): int
    {
        if (self::$lastTimeStamp === $currentTime) {
            self::$sequence++;
            self::$lastTimeStamp = $currentTime;
            return self::$sequence;
        }
        self::$sequence = random_int(0, self::$maxSequence ?? (-1 ^ (-1 << self::$maxSequenceLength)));
        self::$lastTimeStamp = $currentTime;
        return self::$sequence;
    }
}
