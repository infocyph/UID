<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SequenceTimestampException;
use Infocyph\UID\Exceptions\SonyflakeException;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\EpochGuard;
use Infocyph\UID\Support\GetSequence;
use Infocyph\UID\Support\NumericIdCodec;
use Infocyph\UID\Support\OutputFormatter;
use Infocyph\UID\Support\UnsignedDecimal;

final class Sonyflake
{
    use GetSequence;

    private static int $lastWallTime = 0;

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
        return self::decodeNumeric(
            fn(): string => NumericIdCodec::decimalFromBase($encoded, $base, 8),
            null,
        );
    }

    /**
     * Converts 8-byte Sonyflake binary data to decimal string.
     *
     * @throws SonyflakeException
     */
    public static function fromBytes(string $bytes): string
    {
        return self::decodeNumeric(
            fn(): string => NumericIdCodec::decimalFromBytes($bytes, 8),
            'Sonyflake binary data must be exactly 8 bytes',
        );
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
        return $id !== ''
            && $id !== '0'
            && ctype_digit($id)
            && UnsignedDecimal::compare($id, (string) PHP_INT_MAX) <= 0;
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
        if (!self::isValid($id) || UnsignedDecimal::compare($id, (string) PHP_INT_MAX) === 1) {
            throw new SonyflakeException('Invalid Sonyflake ID string');
        }

        $parts = self::extractParts($id, $startTimestamp);

        return [
            'time' => new DateTimeImmutable(
                '@'
                . $parts['seconds']
                . '.'
                . str_pad($parts['fraction'], 6, '0', STR_PAD_LEFT),
            ),
            'sequence' => $parts['sequence'],
            'machine_id' => $parts['machine_id'],
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
        try {
            $resolved = EpochGuard::resolveStartTime(
                $timeString,
                'Invalid start time format',
                'The start time cannot be in the future',
            );
        } catch (\InvalidArgumentException $exception) {
            throw new SonyflakeException($exception->getMessage(), 0, $exception);
        }
        $time = $resolved['time'];
        $current = $resolved['current'];

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
        return self::decodeNumeric(
            fn(): string => NumericIdCodec::bytesFromDecimal(
                $id,
                8,
                self::isValid(...),
                'Invalid Sonyflake ID string',
            ),
            'Unable to convert Sonyflake ID to bytes',
        );
    }

    /**
     * @param callable():string $operation
     * @throws SonyflakeException
     */
    private static function decodeNumeric(callable $operation, ?string $customMessage): string
    {
        try {
            return $operation();
        } catch (\InvalidArgumentException $exception) {
            throw new SonyflakeException($customMessage ?? $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Calculates the elapsed time in 10ms units.
     */
    private static function elapsedTime(int $currentTime, int $startTimestamp): int
    {
        return intdiv($currentTime - $startTimestamp, 10);
    }

    /**
     * Ensures that the elapsed time does not exceed the maximum life cycle of the algorithm.
     *
     * @param int $elapsedTime The elapsed time in milliseconds.
     * @throws SonyflakeException If the elapsed time exceeds the maximum life cycle.
     */
    private static function ensureEffectiveRuntime(int $elapsedTime): void
    {
        if ($elapsedTime < 0) {
            throw new SonyflakeException('Sonyflake epoch must not be in the future');
        }

        if ($elapsedTime > (-1 ^ (-1 << self::$maxTimestampLength))) {
            throw new SonyflakeException('Exceeding the maximum life cycle of the algorithm');
        }
    }

    /**
     * @return array{seconds:string,fraction:string,sequence:int,machine_id:int}
     */
    private static function extractParts(string $id, int $startTimestamp): array
    {
        $binary = decbin((int) $id);
        $tailBitLength = self::$maxMachineIdLength + self::$maxSequenceLength;
        $elapsed = bindec(substr($binary, 0, strlen($binary) - $tailBitLength));
        $timestamp = (string) ($startTimestamp + ($elapsed * 10));
        $timeParts = str_split($timestamp, 10);

        return [
            'seconds' => $timeParts[0],
            'fraction' => $timeParts[1] ?? '0',
            'sequence' => (int) bindec(substr($binary, -1 * self::$maxSequenceLength)),
            'machine_id' => (int) bindec(substr($binary, -1 * $tailBitLength, self::$maxMachineIdLength)),
        ];
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

        $currentTime = (int) floor(microtime(true) * 1000);
        if ($currentTime < self::$lastWallTime) {
            if ($clockBackwardPolicy === ClockBackwardPolicy::THROW) {
                throw new SonyflakeException('Clock moved backwards while generating Sonyflake ID');
            }

            $currentTime = self::waitUntilWallTime(self::$lastWallTime);
        }

        $elapsedTime = self::elapsedTime($currentTime, $startTimestamp);
        self::ensureEffectiveRuntime($elapsedTime);
        $sequenceType = 'sonyflake_' . $startTimestamp;

        while (true) {
            try {
                $sequence = self::sequence(
                    $elapsedTime,
                    $machineId,
                    $sequenceType,
                    $sequenceProvider,
                );
            } catch (SequenceTimestampException $exception) {
                if ($clockBackwardPolicy === ClockBackwardPolicy::THROW) {
                    throw new SonyflakeException(
                        'Clock moved backwards while generating Sonyflake ID',
                        0,
                        $exception,
                    );
                }

                $elapsedTime = self::waitUntilElapsed($exception->lastTimestamp, $startTimestamp);

                continue;
            }

            if ($sequence <= (-1 ^ (-1 << self::$maxSequenceLength))) {
                break;
            }

            $elapsedTime = self::waitUntilElapsed($elapsedTime, $startTimestamp);
        }
        self::$lastWallTime = max($currentTime, $startTimestamp + ($elapsedTime * 10));

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
        return self::$startTime ??= 1_577_836_800_000;
    }

    private static function waitUntilElapsed(int $elapsedTime, int $startTimestamp): int
    {
        $next = self::elapsedTime((int) floor(microtime(true) * 1000), $startTimestamp);
        while ($next <= $elapsedTime) {
            usleep(1000);
            $next = self::elapsedTime((int) floor(microtime(true) * 1000), $startTimestamp);
        }

        return $next;
    }

    private static function waitUntilWallTime(int $lastTime): int
    {
        do {
            usleep(1000);
            $currentTime = (int) floor(microtime(true) * 1000);
        } while ($currentTime < $lastTime);

        return $currentTime;
    }
}
