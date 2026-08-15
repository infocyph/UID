<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SequenceTimestampException;
use Infocyph\UID\Exceptions\SonyflakeException;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\GetSequence;
use Infocyph\UID\Support\NumericIdCodec;
use Infocyph\UID\Support\UnsignedDecimal;

final class Sonyflake
{
    use GetSequence;

    private const DEFAULT_EPOCH = 1_577_836_800_000;

    private const MACHINE_BITS = 16;

    private const SEQUENCE_BITS = 8;

    private const TIMESTAMP_BITS = 39;

    /** @var array<string, int> */
    private static array $lastWallTimeByDomain = [];

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
        return self::generateInternal(
            $machineId,
            self::getStartTimeStamp(),
            ClockBackwardPolicy::WAIT,
        );
    }

    /**
     * Generates Sonyflake using configuration object.
     *
     * @throws SonyflakeException|FileLockException
     */
    public static function generateWithConfig(SonyflakeConfig $config): string
    {
        return self::generateInternal(
            $config->resolveMachineId(),
            $config->resolveCustomEpochMs() ?? self::getStartTimeStamp(),
            $config->clockBackwardPolicy,
            $config->sequenceProvider,
        );
    }

    /**
     * Checks whether a Sonyflake ID string has a valid numeric shape.
     */
    public static function isValid(string $id): bool
    {
        return $id !== ''
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

        if ($elapsedTime > (-1 ^ (-1 << self::TIMESTAMP_BITS))) {
            throw new SonyflakeException('Exceeding the maximum life cycle of the algorithm');
        }
    }

    /**
     * @return array{seconds:string,fraction:string,sequence:int,machine_id:int}
     */
    private static function extractParts(string $id, int $startTimestamp): array
    {
        $numericId = (int) $id;
        $elapsed = $numericId >> 24;
        $timestamp = $startTimestamp + ($elapsed * 10);

        return [
            'seconds' => (string) intdiv($timestamp, 1000),
            'fraction' => (string) (($timestamp % 1000) * 1000),
            'sequence' => $numericId & 0xff,
            'machine_id' => ($numericId >> 8) & 0xffff,
        ];
    }

    /**
     * @throws SonyflakeException|FileLockException
     */
    private static function generateInternal(
        int $machineId,
        int $startTimestamp,
        ClockBackwardPolicy $clockBackwardPolicy,
        ?SequenceProviderInterface $sequenceProvider = null,
    ): string {
        $maxMachineID = -1 ^ (-1 << self::MACHINE_BITS);
        if ($machineId < 0 || $machineId > $maxMachineID) {
            throw new SonyflakeException("Invalid machine ID, must be between 0 ~ $maxMachineID.");
        }

        $resolvedSequenceProvider = self::resolveSequenceProvider($sequenceProvider);
        $currentTime = (int) floor(microtime(true) * 1000);
        $domainKey = $startTimestamp . ':' . $machineId . ':' . spl_object_id($resolvedSequenceProvider);
        $lastWallTime = self::$lastWallTimeByDomain[$domainKey] ?? 0;
        if ($currentTime < $lastWallTime) {
            if ($clockBackwardPolicy === ClockBackwardPolicy::THROW) {
                throw new SonyflakeException('Clock moved backwards while generating Sonyflake ID');
            }

            $currentTime = self::waitUntilWallTime($lastWallTime);
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
                    $resolvedSequenceProvider,
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

            if ($sequence < 1) {
                throw new SonyflakeException('Sonyflake sequence provider must return a positive allocation');
            }

            if ($sequence <= (-1 ^ (-1 << self::SEQUENCE_BITS)) + 1) {
                --$sequence;

                break;
            }

            $elapsedTime = self::waitUntilElapsed($elapsedTime, $startTimestamp);
        }
        self::$lastWallTimeByDomain[$domainKey] = max($currentTime, $startTimestamp + ($elapsedTime * 10));

        self::ensureEffectiveRuntime($elapsedTime);

        return (string) ($elapsedTime << (self::MACHINE_BITS + self::SEQUENCE_BITS)
            | ($machineId << self::SEQUENCE_BITS)
            | ($sequence));
    }

    /**
     * Retrieves the start timestamp.
     */
    private static function getStartTimeStamp(): int
    {
        return self::DEFAULT_EPOCH;
    }

    private static function resolveSequenceProvider(?SequenceProviderInterface $provider): SequenceProviderInterface
    {
        return $provider ?? self::$sequenceProvider ??= new FilesystemSequenceProvider();
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
