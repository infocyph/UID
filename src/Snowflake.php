<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SnowflakeException;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\OutputFormatter;

final class Snowflake
{
    use GetSequence;

    private static int $lastTimestamp = 0;

    private static int $maxDatacenterLength = 5;

    private static int $maxSequenceLength = 12;

    private static int $maxTimestampLength = 41;

    private static int $maxWorkIdLength = 5;

    private static ?int $startTime = null;

    /**
     * Decodes one of bases: 16, 32, 36, 58, 62 into Snowflake decimal.
     *
     * @throws SnowflakeException
     */
    public static function fromBase(string $encoded, int $base): string
    {
        try {
            return self::fromBytes(BaseEncoder::decodeToBytes($encoded, $base, 8));
        } catch (\InvalidArgumentException $exception) {
            throw new SnowflakeException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Converts 8-byte Snowflake binary data to decimal string.
     *
     * @throws SnowflakeException
     */
    public static function fromBytes(string $bytes): string
    {
        if (strlen($bytes) !== 8) {
            throw new SnowflakeException('Snowflake binary data must be exactly 8 bytes');
        }

        $decimal = '0';
        foreach (str_split(bin2hex($bytes)) as $char) {
            $decimal = bcadd(bcmul($decimal, '16'), (string) hexdec($char));
        }

        return $decimal;
    }

    /**
     * Generates a unique snowflake ID.
     *
     * @param int $datacenter The ID of the datacenter (default: 0)
     * @param int $workerId The ID of the worker (default: 0)
     * @return string The generated snowflake ID
     * @throws SnowflakeException|FileLockException
     */
    public static function generate(int $datacenter = 0, int $workerId = 0): string
    {
        return (string) self::generateInternal(
            $datacenter,
            $workerId,
            self::getStartTimeStamp(),
            ClockBackwardPolicy::WAIT,
            IdOutputType::STRING,
        );
    }

    /**
     * Generates Snowflake using configuration object.
     *
     * @throws SnowflakeException|FileLockException
     */
    public static function generateWithConfig(SnowflakeConfig $config): int|string
    {
        [$datacenterId, $workerId] = $config->resolveNode();

        return self::generateInternal(
            $datacenterId,
            $workerId,
            $config->resolveCustomEpochMs() ?? self::getStartTimeStamp(),
            $config->clockBackwardPolicy,
            $config->outputType,
            $config->sequenceProvider,
        );
    }

    /**
     * Checks whether a Snowflake ID string has a valid numeric shape.
     */
    public static function isValid(string $id): bool
    {
        return preg_match('/^\d+$/', $id) === 1 && $id !== '0';
    }

    /**
     * Parse the given ID into components.
     *
     * @param string $id The ID to parse.
     * @return array{time: DateTimeImmutable, sequence: int, worker_id: int, datacenter_id: int}
     * @throws Exception
     */
    public static function parse(string $id): array
    {
        return self::parseWithEpoch($id, self::getStartTimeStamp());
    }

    /**
     * Parse Snowflake ID using a custom epoch in milliseconds.
     *
     * @return array{time: DateTimeImmutable, sequence: int, worker_id: int, datacenter_id: int}
     * @throws Exception
     */
    public static function parseWithEpoch(string $id, int $startTimestamp): array
    {
        $id = decbin((int) $id);
        $time = str_split((string) (bindec(substr($id, 0, -22)) + $startTimestamp), 10);

        return [
            'time' => new DateTimeImmutable(
                '@'
                . $time[0]
                . '.'
                . str_pad($time[1], 6, '0', STR_PAD_LEFT),
            ),
            'sequence' => (int) bindec(substr($id, -12)),
            'worker_id' => (int) bindec(substr($id, -17, 5)),
            'datacenter_id' => (int) bindec(substr($id, -22, 5)),
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
                    'The current microtime - start_time is not allowed to exceed -1 ^ (-1 << %d),\n                    You can reset the start time to fix this',
                    self::$maxTimestampLength,
                ),
            );
        }

        self::$startTime = $time * 1000;
    }

    /**
     * Encodes Snowflake bytes into one of bases: 16, 32, 36, 58, 62.
     *
     * @throws SnowflakeException
     */
    public static function toBase(string $id, int $base): string
    {
        return BaseEncoder::encodeBytes(self::toBytes($id), $base);
    }

    /**
     * Converts a Snowflake decimal string to 8-byte binary representation.
     *
     * @throws SnowflakeException
     */
    public static function toBytes(string $id): string
    {
        if (!self::isValid($id)) {
            throw new SnowflakeException('Invalid Snowflake ID string');
        }

        $hex = '';
        $value = $id;
        while ($value !== '0') {
            $remainder = (int) bcmod($value, '16');
            $hex = dechex($remainder) . $hex;
            $value = bcdiv($value, '16', 0);
        }

        $hex = str_pad($hex, 16, '0', STR_PAD_LEFT);
        $bytes = hex2bin($hex);
        $bytes !== false || throw new SnowflakeException('Unable to convert Snowflake ID to bytes');

        return $bytes;
    }

    /**
     * @throws SnowflakeException
     */
    private static function assertNodeIds(int $datacenter, int $workerId): void
    {
        $maxDataCenter = -1 ^ (-1 << self::$maxDatacenterLength);
        $maxWorkId = -1 ^ (-1 << self::$maxWorkIdLength);

        if ($datacenter > $maxDataCenter || $datacenter < 0) {
            throw new SnowflakeException("Invalid datacenter ID, must be between 0 ~ $maxDataCenter.");
        }

        if ($workerId > $maxWorkId || $workerId < 0) {
            throw new SnowflakeException("Invalid worker ID, must be between 0 ~ $maxWorkId.");
        }
    }

    /**
     * @throws SnowflakeException|FileLockException
     */
    private static function generateInternal(
        int $datacenter,
        int $workerId,
        int $startTimestamp,
        ClockBackwardPolicy $clockBackwardPolicy,
        IdOutputType $outputType,
        ?SequenceProviderInterface $sequenceProvider = null,
    ): int|string {
        self::assertNodeIds($datacenter, $workerId);

        $currentTime = (int) (new DateTimeImmutable('now'))->format('Uv');
        if ($currentTime < self::$lastTimestamp) {
            if ($clockBackwardPolicy === ClockBackwardPolicy::THROW) {
                throw new SnowflakeException('Clock moved backwards while generating Snowflake ID');
            }

            $currentTime = self::waitUntil(self::$lastTimestamp);
        }

        $sequenceKey = ($datacenter << self::$maxWorkIdLength) | $workerId;
        while (($sequence = self::sequence(
            $currentTime,
            $sequenceKey,
            'snowflake',
            $sequenceProvider,
        )) > (-1 ^ (-1 << self::$maxSequenceLength))) {
            ++$currentTime;
        }
        self::$lastTimestamp = $currentTime;

        $workerLeftMoveLength = self::$maxSequenceLength;
        $datacenterLeftMoveLength = self::$maxWorkIdLength + $workerLeftMoveLength;
        $timestampLeftMoveLength = self::$maxDatacenterLength + $datacenterLeftMoveLength;

        $id = (string) ((($currentTime - $startTimestamp) << $timestampLeftMoveLength)
            | ($datacenter << $datacenterLeftMoveLength)
            | ($workerId << $workerLeftMoveLength)
            | ($sequence));

        return OutputFormatter::formatNumeric($id, $outputType);
    }

    /**
     * Retrieves the start timestamp.
     */
    private static function getStartTimeStamp(): int
    {
        return self::$startTime ??= (strtotime('2020-01-01 00:00:00') * 1000);
    }

    private static function waitUntil(int $timestamp): int
    {
        do {
            usleep(1000);
            $now = (int) (new DateTimeImmutable('now'))->format('Uv');
        } while ($now < $timestamp);

        return $now;
    }
}
