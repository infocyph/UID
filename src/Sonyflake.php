<?php

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SonyflakeException;

final class Sonyflake
{
    use GetSequence;

    private static int $maxTimestampLength = 39;
    private static int $maxMachineIdLength = 16;
    private static int $maxSequenceLength = 8;
    private static ?int $startTime = null;

    /**
     * Generates a unique identifier using the SonyFlake algorithm.
     *
     * @param int $machineId The machine identifier. Must be between 0 and the maximum machine ID.
     * @return string The generated unique identifier.
     * @throws SonyflakeException|FileLockException
     */
    public static function generate(int $machineId = 0): string
    {
        $maxMachineID = -1 ^ (-1 << self::$maxMachineIdLength);
        if ($machineId < 0 || $machineId > $maxMachineID) {
            throw new SonyflakeException("Invalid machine ID, must be between 0 ~ $maxMachineID.");
        }
        $now = (int)(new DateTimeImmutable('now'))->format('Uv');
        $elapsedTime = self::elapsedTime();
        while (($sequence = self::sequence($now, $machineId, 'sonyflake')) > (-1 ^ (-1 << self::$maxSequenceLength))) {
            $nextMillisecond = self::elapsedTime();
            while ($nextMillisecond === $elapsedTime) {
                ++$nextMillisecond;
            }
            $elapsedTime = $nextMillisecond;
        }
        self::ensureEffectiveRuntime($elapsedTime);

        return (string)($elapsedTime << (self::$maxMachineIdLength + self::$maxSequenceLength)
            | ($machineId << self::$maxSequenceLength)
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
        $length = self::$maxMachineIdLength + self::$maxSequenceLength;
        $time = str_split(bindec(substr($id, 0, strlen($id) - $length)) * 10 + self::getStartTimeStamp(), 10);

        return [
            'time' => new DateTimeImmutable(
                '@'
                . $time[0]
                . '.'
                . str_pad($time[1], 6, '0', STR_PAD_LEFT)
            ),
            'sequence' => bindec(substr($id, -1 * self::$maxSequenceLength)),
            'machine_id' => bindec(substr($id, -1 * $length, self::$maxMachineIdLength)),
        ];
    }

    /**
     * Sets the start timestamp for the SonyFlake algorithm.
     *
     * @param string $timeString The start time in string format.
     * @return void
     * @throws SonyflakeException
     */
    public static function setStartTimeStamp(string $timeString): void
    {
        $time = strtotime($timeString);
        $current = time();

        if ($time > $current) {
            throw new SonyflakeException('The start time cannot be in the future');
        }

        self::ensureEffectiveRuntime(floor(($current - $time) / 10) | 0);
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
     * Ensures that the elapsed time does not exceed the maximum life cycle of the algorithm.
     *
     * @param int $elapsedTime The elapsed time in milliseconds.
     * @return void
     * @throws SonyflakeException If the elapsed time exceeds the maximum life cycle.
     */
    private static function ensureEffectiveRuntime(int $elapsedTime): void
    {
        if ($elapsedTime > (-1 ^ (-1 << self::$maxTimestampLength))) {
            throw new SonyflakeException('Exceeding the maximum life cycle of the algorithm');
        }
    }

    /**
     * Calculates the elapsed time in milliseconds.
     *
     * @return int unit: 10ms.
     */
    private static function elapsedTime(): int
    {
        return floor(((new DateTimeImmutable('now'))->format('Uv') - self::getStartTimeStamp()) / 10) | 0;
    }
}
