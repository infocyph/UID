<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Enums\SonyflakeFormat;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SequenceTimestampException;
use Infocyph\UID\Exceptions\SonyflakeException;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\GetSequence;
use Infocyph\UID\Support\NumericIdCodec;
use Infocyph\UID\Support\UnsignedDecimal;

final class Sonyflake
{
    use GetSequence;

    private const int DEFAULT_EPOCH = 1_577_836_800_000;

    private const int MACHINE_BITS = 16;

    private const int SEQUENCE_BITS = 8;

    private const int UPSTREAM_DEFAULT_EPOCH = 1_409_529_600_000;

    private const int TIMESTAMP_BITS = 39;

    private const int WAIT_TIMEOUT_MICROS = 1_000_000;

    /** @var \WeakMap<SequenceProviderInterface, \ArrayObject<string, int>>|null */
    private static ?\WeakMap $lastWallTimeByProvider = null;

    /**
     * Decodes one of bases: 16, 32, 36, 58, 62 into Sonyflake decimal.
     *
     * @throws SonyflakeException
     */
    public static function fromBase(string $encoded, int $base): string
    {
        $id = self::decodeNumeric(
            fn(): string => NumericIdCodec::decimalFromBase($encoded, $base, 8),
            null,
        );

        return self::assertDecodedId($id);
    }

    /**
     * Converts 8-byte Sonyflake binary data to decimal string.
     *
     * @throws SonyflakeException
     */
    public static function fromBytes(string $bytes): string
    {
        $id = self::decodeNumeric(
            fn(): string => NumericIdCodec::decimalFromBytes($bytes, 8),
            'Sonyflake binary data must be exactly 8 bytes',
        );

        return self::assertDecodedId($id);
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
            self::getStartTimeStamp(SonyflakeFormat::UID),
            ClockBackwardPolicy::WAIT,
            format: SonyflakeFormat::UID,
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
            $config->resolveCustomEpochMs() ?? self::getStartTimeStamp($config->format),
            $config->clockBackwardPolicy,
            $config->sequenceProvider,
            $config->runtime,
            $config->format,
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
    public static function parse(string $id, SonyflakeFormat $format = SonyflakeFormat::UID): array
    {
        return self::parseWithEpoch($id, self::getStartTimeStamp($format), $format);
    }

    /**
     * Parse Sonyflake using custom epoch in milliseconds.
     *
     * @return array{time: DateTimeImmutable, sequence: int, machine_id: int}
     * @throws Exception
     */
    public static function parseWithEpoch(
        string $id,
        int $startTimestamp,
        SonyflakeFormat $format = SonyflakeFormat::UID,
    ): array
    {
        if (!self::isValid($id) || UnsignedDecimal::compare($id, (string) PHP_INT_MAX) === 1) {
            throw new SonyflakeException('Invalid Sonyflake ID string');
        }

        $parts = self::extractParts($id, $startTimestamp, $format);

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
     * @return array{0:int,1:int}
     */
    private static function allocateSequence(
        SequenceProviderInterface $provider,
        int $machineId,
        int $startTimestamp,
        int $elapsedTime,
        ClockBackwardPolicy $policy,
        ?GenerationContext $runtime,
        SonyflakeFormat $format,
    ): array {
        $sequenceType = $format === SonyflakeFormat::UID
            ? 'sonyflake_' . $startTimestamp
            : 'sonyflake_upstream_' . $startTimestamp;

        while (true) {
            try {
                $allocation = self::sequence($elapsedTime, $machineId, $sequenceType, $provider);
            } catch (SequenceTimestampException $exception) {
                if ($policy === ClockBackwardPolicy::THROW) {
                    throw new SonyflakeException(
                        'Clock moved backwards while generating Sonyflake ID',
                        0,
                        $exception,
                    );
                }

                $elapsedTime = self::waitUntilElapsed($exception->lastTimestamp, $startTimestamp, $runtime);

                continue;
            }

            if ($allocation < 1) {
                throw new SonyflakeException('Sonyflake sequence provider must return a positive allocation');
            }
            if ($allocation <= (-1 ^ (-1 << self::SEQUENCE_BITS)) + 1) {
                return [$elapsedTime, $allocation - 1];
            }

            $elapsedTime = self::waitUntilElapsed($elapsedTime, $startTimestamp, $runtime);
        }
    }

    private static function assertDecodedId(string $id): string
    {
        if (!self::isValid($id)) {
            throw new SonyflakeException('Decoded Sonyflake ID exceeds the supported signed domain');
        }

        return $id;
    }

    private static function assertMachineId(int $machineId): void
    {
        $maximum = -1 ^ (-1 << self::MACHINE_BITS);
        if ($machineId < 0 || $machineId > $maximum) {
            throw new SonyflakeException("Invalid machine ID, must be between 0 ~ $maximum.");
        }
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
    private static function extractParts(
        string $id,
        int $startTimestamp,
        SonyflakeFormat $format,
    ): array {
        $numericId = (int) $id;
        $elapsed = $numericId >> 24;
        $timestamp = $startTimestamp + ($elapsed * 10);

        return [
            'seconds' => (string) intdiv($timestamp, 1000),
            'fraction' => (string) (($timestamp % 1000) * 1000),
            'sequence' => $format === SonyflakeFormat::UPSTREAM
                ? ($numericId >> 16) & 0xff
                : $numericId & 0xff,
            'machine_id' => $format === SonyflakeFormat::UPSTREAM
                ? $numericId & 0xffff
                : ($numericId >> 8) & 0xffff,
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
        ?GenerationContext $runtime = null,
        SonyflakeFormat $format = SonyflakeFormat::UID,
    ): string {
        self::assertMachineId($machineId);

        $provider = self::resolveSequenceProvider($sequenceProvider);
        self::$lastWallTimeByProvider ??= new \WeakMap();

        /** @var \ArrayObject<string, int>|null $providerState */
        $providerState = self::$lastWallTimeByProvider[$provider] ?? null;
        if ($providerState === null) {
            /** @var \ArrayObject<string, int> $providerState */
            $providerState = new \ArrayObject();
            self::$lastWallTimeByProvider[$provider] = $providerState;
        }

        $domainKey = $format->value . ':' . $startTimestamp . ':' . $machineId;
        $currentTime = self::resolveWallTime(
            self::nowMilliseconds($runtime),
            $providerState[$domainKey] ?? 0,
            $clockBackwardPolicy,
            $runtime,
        );
        $elapsedTime = self::elapsedTime($currentTime, $startTimestamp);
        self::ensureEffectiveRuntime($elapsedTime);

        [$elapsedTime, $sequence] = self::allocateSequence(
            $provider,
            $machineId,
            $startTimestamp,
            $elapsedTime,
            $clockBackwardPolicy,
            $runtime,
            $format,
        );
        $providerState[$domainKey] = max($currentTime, $startTimestamp + ($elapsedTime * 10));
        self::ensureEffectiveRuntime($elapsedTime);

        return self::packId($elapsedTime, $machineId, $sequence, $format);
    }

    /**
     * Retrieves the start timestamp.
     */
    private static function getStartTimeStamp(SonyflakeFormat $format): int
    {
        return $format === SonyflakeFormat::UPSTREAM
            ? self::UPSTREAM_DEFAULT_EPOCH
            : self::DEFAULT_EPOCH;
    }

    private static function nowMilliseconds(?GenerationContext $runtime): int
    {
        return $runtime?->nowMilliseconds() ?? (int) floor(microtime(true) * 1000);
    }

    private static function packId(
        int $elapsedTime,
        int $machineId,
        int $sequence,
        SonyflakeFormat $format,
    ): string {
        if ($format === SonyflakeFormat::UPSTREAM) {
            return (string) (
                ($elapsedTime << (self::MACHINE_BITS + self::SEQUENCE_BITS))
                | ($sequence << self::MACHINE_BITS)
                | $machineId
            );
        }

        return (string) (
            ($elapsedTime << (self::MACHINE_BITS + self::SEQUENCE_BITS))
            | ($machineId << self::SEQUENCE_BITS)
            | $sequence
        );
    }

    private static function resolveSequenceProvider(?SequenceProviderInterface $provider): SequenceProviderInterface
    {
        return $provider ?? self::$sequenceProvider ??= new FilesystemSequenceProvider();
    }

    private static function resolveWallTime(
        int $currentTime,
        int $lastWallTime,
        ClockBackwardPolicy $policy,
        ?GenerationContext $runtime,
    ): int {
        if ($currentTime >= $lastWallTime) {
            return $currentTime;
        }
        if ($policy === ClockBackwardPolicy::THROW) {
            throw new SonyflakeException('Clock moved backwards while generating Sonyflake ID');
        }

        return self::waitUntilWallTime($lastWallTime, $runtime);
    }

    private static function waitUntilElapsed(
        int $elapsedTime,
        int $startTimestamp,
        ?GenerationContext $runtime,
    ): int {
        $deadline = $runtime?->waitDeadlineNanoseconds()
            ?? hrtime(true) + (self::WAIT_TIMEOUT_MICROS * 1_000);

        while (($next = self::elapsedTime(self::nowMilliseconds($runtime), $startTimestamp)) <= $elapsedTime) {
            if (hrtime(true) >= $deadline) {
                throw new SonyflakeException('Timed out waiting for the next Sonyflake timestamp');
            }

            if ($runtime !== null) {
                $runtime->sleepMicroseconds(1_000);
            } else {
                usleep(1_000);
            }
        }

        return $next;
    }

    private static function waitUntilWallTime(int $lastTime, ?GenerationContext $runtime): int
    {
        $deadline = $runtime?->waitDeadlineNanoseconds()
            ?? hrtime(true) + (self::WAIT_TIMEOUT_MICROS * 1_000);

        while (($currentTime = self::nowMilliseconds($runtime)) < $lastTime) {
            if (hrtime(true) >= $deadline) {
                throw new SonyflakeException('Timed out waiting for the Sonyflake clock to recover');
            }

            if ($runtime !== null) {
                $runtime->sleepMicroseconds(1_000);
            } else {
                usleep(1_000);
            }
        }

        return $currentTime;
    }

}
