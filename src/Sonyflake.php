<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SonyflakeException;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\OutputFormatter;

final class Sonyflake
{
    use GetSequence;

    private static int $lastElapsedTime = 0;

    private static int $maxMachineIdLength = 16;

    private static int $maxSequenceLength = 8;

    private static int $maxTimestampLength = 39;

    private static ?int $startTime = null;

    /**
     * Decodes one of bases: 16, 32, 36, 58, 62 into Sonyflake decimal.
     *
     * @throws SonyflakeException
     */
    public static function fromBase(string $encoded, int $base): string
    {
        try {
            return self::fromBytes(BaseEncoder::decodeToBytes($encoded, $base, 8));
        } catch (\InvalidArgumentException $exception) {
            throw new SonyflakeException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Converts 8-byte Sonyflake binary data to decimal string.
     *
     * @throws SonyflakeException
     */
    public static function fromBytes(string $bytes): string
    {
        if (strlen($bytes) !== 8) {
            throw new SonyflakeException('Sonyflake binary data must be exactly 8 bytes');
        }

        $decimal = '0';
        foreach (str_split(bin2hex($bytes)) as $char) {
            $decimal = bcadd(bcmul($decimal, '16'), (string) hexdec($char));
        }

        return $decimal;
    }

    /**
     * Generates a unique identifier using the SonyFlake algorithm.
     *
     * @param int $machineId The machine identifier. Must be between 0 and the maximum machine ID.
     * @return string The generated unique identifier.
     * @throws SonyflakeException|FileLockException
     */
    public static function generate(int $machineId = 0): string
    {
        return (string) self::generateInternal(
            $machineId,
            self::getStartTimeStamp(),
            ClockBackwardPolicy::WAIT,
            IdOutputType::STRING,
        );
    }

    /**
     * Generates Sonyflake using configuration object.
     *
     * @throws SonyflakeException|FileLockException
     */
    public static function generateWithConfig(SonyflakeConfig $config): int|string
    {
        return self::generateInternal(
            $config->resolveMachineId(),
            $config->resolveCustomEpochMs() ?? self::getStartTimeStamp(),
            $config->clockBackwardPolicy,
            $config->outputType,
            $config->sequenceProvider,
        );
    }

    /**
     * Checks whether a Sonyflake ID string has a valid numeric shape.
     */
    public static function isValid(string $id): bool
    {
        return preg_match('/^\d+$/', $id) === 1 && $id !== '0';
    }

    /**
     * Parse the given ID into components.
     *
     * @param string $id The ID to parse.
     * @return array{time: DateTimeImmutable, sequence: int, machine_id: int}
     * @throws Exception
     */
    public static function parse(string $id): array
    {
        return self::parseWithEpoch($id, self::getStartTimeStamp());
    }

    /**
     * Parse Sonyflake using custom epoch in milliseconds.
     *
     * @return array{time: DateTimeImmutable, sequence: int, machine_id: int}
     * @throws Exception
     */
    public static function parseWithEpoch(string $id, int $startTimestamp): array
    {
        $id = decbin((int) $id);
        $length = self::$maxMachineIdLength + self::$maxSequenceLength;
        $time = str_split((string) (bindec(substr($id, 0, strlen($id) - $length)) * 10 + $startTimestamp), 10);

        return [
            'time' => new DateTimeImmutable(
                '@'
                . $time[0]
                . '.'
                . str_pad($time[1], 6, '0', STR_PAD_LEFT),
            ),
            'sequence' => (int) bindec(substr($id, -1 * self::$maxSequenceLength)),
            'machine_id' => (int) bindec(substr($id, -1 * $length, self::$maxMachineIdLength)),
        ];
    }

    /**
     * Sets the start timestamp for the SonyFlake algorithm.
     *
     * @param string $timeString The start time in string format.
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
     * Encodes Sonyflake bytes into one of bases: 16, 32, 36, 58, 62.
     *
     * @throws SonyflakeException
     */
    public static function toBase(string $id, int $base): string
    {
        return BaseEncoder::encodeBytes(self::toBytes($id), $base);
    }

    /**
     * Converts a Sonyflake decimal string to 8-byte binary representation.
     *
     * @throws SonyflakeException
     */
    public static function toBytes(string $id): string
    {
        if (!self::isValid($id)) {
            throw new SonyflakeException('Invalid Sonyflake ID string');
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
        $bytes !== false || throw new SonyflakeException('Unable to convert Sonyflake ID to bytes');

        return $bytes;
    }

    /**
     * Calculates the elapsed time in 10ms units.
     */
    private static function elapsedTime(int $startTimestamp): int
    {
        return floor(((new DateTimeImmutable('now'))->format('Uv') - $startTimestamp) / 10) | 0;
    }

    /**
     * Ensures that the elapsed time does not exceed the maximum life cycle of the algorithm.
     *
     * @param int $elapsedTime The elapsed time in milliseconds.
     * @throws SonyflakeException If the elapsed time exceeds the maximum life cycle.
     */
    private static function ensureEffectiveRuntime(int $elapsedTime): void
    {
        if ($elapsedTime > (-1 ^ (-1 << self::$maxTimestampLength))) {
            throw new SonyflakeException('Exceeding the maximum life cycle of the algorithm');
        }
    }

    /**
     * @throws SonyflakeException|FileLockException
     */
    private static function generateInternal(
        int $machineId,
        int $startTimestamp,
        ClockBackwardPolicy $clockBackwardPolicy,
        IdOutputType $outputType,
        ?SequenceProviderInterface $sequenceProvider = null,
    ): int|string {
        $maxMachineID = -1 ^ (-1 << self::$maxMachineIdLength);
        if ($machineId < 0 || $machineId > $maxMachineID) {
            throw new SonyflakeException("Invalid machine ID, must be between 0 ~ $maxMachineID.");
        }

        $elapsedTime = self::elapsedTime($startTimestamp);

        if ($elapsedTime < self::$lastElapsedTime) {
            if ($clockBackwardPolicy === ClockBackwardPolicy::THROW) {
                throw new SonyflakeException('Clock moved backwards while generating Sonyflake ID');
            }

            $elapsedTime = self::waitUntilElapsed(self::$lastElapsedTime, $startTimestamp);
        }

        while (($sequence = self::sequence(
            $elapsedTime,
            $machineId,
            'sonyflake',
            $sequenceProvider,
        )) > (-1 ^ (-1 << self::$maxSequenceLength))) {
            $elapsedTime = self::waitUntilElapsed($elapsedTime, $startTimestamp);
        }
        self::$lastElapsedTime = $elapsedTime;

        self::ensureEffectiveRuntime($elapsedTime);

        $id = (string) ($elapsedTime << (self::$maxMachineIdLength + self::$maxSequenceLength)
            | ($machineId << self::$maxSequenceLength)
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

    private static function waitUntilElapsed(int $elapsedTime, int $startTimestamp): int
    {
        $next = self::elapsedTime($startTimestamp);
        while ($next <= $elapsedTime) {
            usleep(1000);
            $next = self::elapsedTime($startTimestamp);
        }

        return $next;
    }
}
