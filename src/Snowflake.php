<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SequenceTimestampException;
use Infocyph\UID\Exceptions\SnowflakeException;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\GetSequence;
use Infocyph\UID\Support\NumericConversion;
use Infocyph\UID\Support\UnsignedDecimal;

final class Snowflake
{
    use GetSequence;

    private const int DATACENTER_BITS = 5;

    private const int DEFAULT_EPOCH = 1_577_836_800_000;

    private const int MAX_PROVIDER_DOMAINS = 1024;

    private const int SEQUENCE_BITS = 12;

    private const int TIMESTAMP_BITS = 41;

    private const int WAIT_TIMEOUT_MICROS = 1_000_000;

    private const int WORKER_BITS = 5;

    /** @var \WeakMap<SequenceProviderInterface, \ArrayObject<string, array{timestamp:int, sequence:int}>>|null */
    private static ?\WeakMap $lastStateByProvider = null;

    /**
     * Decodes one of bases: 16, 32, 36, 58, 62 into Snowflake decimal.
     *
     * @throws SnowflakeException
     */
    public static function fromBase(string $encoded, int $base): string {
        return self::decodeNumericBase($encoded, $base);
    }

    /**
     * Converts 8-byte Snowflake binary data to decimal string.
     *
     * @throws SnowflakeException
     */
    public static function fromBytes(string $bytes): string {
        return self::decodeNumericBytes($bytes);
    }

    /**
     * Generates a unique snowflake ID.
     *
     * @param int $datacenter The ID of the datacenter (default: 0)
     * @param int $workerId The ID of the worker (default: 0)
     * @return string The generated snowflake ID
     * @throws SnowflakeException|FileLockException
     */
    public static function generate(int $datacenter = 0, int $workerId = 0): string {
        return self::generateInternal(
            $datacenter,
            $workerId,
            self::getStartTimeStamp(),
            ClockBackwardPolicy::WAIT,
        );
    }

    /**
     * Generates Snowflake using configuration object.
     *
     * @throws SnowflakeException|FileLockException
     */
    public static function generateWithConfig(SnowflakeConfig $config): string {
        [$datacenterId, $workerId] = $config->resolveNode();
        $customEpoch = $config->resolveCustomEpochMs();

        return self::generateInternal(
            $datacenterId,
            $workerId,
            $customEpoch ?? self::getStartTimeStamp(),
            $config->clockBackwardPolicy,
            $config->sequenceProvider,
            $config->runtime,
        );
    }

    /**
     * Checks whether a Snowflake ID string has a valid numeric shape.
     */
    public static function isValid(string $id): bool {
        return $id !== ''
            && ctype_digit($id)
            && UnsignedDecimal::compare($id, (string) PHP_INT_MAX) <= 0;
    }

    /**
     * Parse the given ID into components.
     *
     * @param string $id The ID to parse.
     * @return array{time: DateTimeImmutable, sequence: int, worker_id: int, datacenter_id: int}
     * @throws Exception
     */
    public static function parse(string $id): array {
        return self::parseWithEpoch(
            id: $id,
            startTimestamp: self::getStartTimeStamp(),
        );
    }

    /**
     * Parse Snowflake ID using a custom epoch in milliseconds.
     *
     * @return array{time: DateTimeImmutable, sequence: int, worker_id: int, datacenter_id: int}
     * @throws Exception
     */
    public static function parseWithEpoch(string $id, int $startTimestamp): array {
        if (!self::isValid($id) || UnsignedDecimal::compare($id, (string) PHP_INT_MAX) === 1) {
            throw new SnowflakeException('Invalid Snowflake ID string');
        }

        $numericId = (int) $id;
        $timestamp = ($numericId >> 22) + $startTimestamp;
        [$seconds, $fraction] = self::timestampParts($timestamp);

        return [
            'time' => new DateTimeImmutable(
                '@'
                . $seconds
                . '.'
                . str_pad($fraction, 6, '0', STR_PAD_LEFT),
            ),
            'sequence' => $numericId & 0xfff,
            'worker_id' => ($numericId >> 12) & 0x1f,
            'datacenter_id' => ($numericId >> 17) & 0x1f,
        ];
    }

    /**
     * Encodes Snowflake bytes into one of bases: 16, 32, 36, 58, 62.
     *
     * @throws SnowflakeException
     */
    public static function toBase(string $id, int $base): string {
        return BaseEncoder::encodeBytes(self::toBytes($id), $base);
    }

    /**
     * Converts a Snowflake decimal string to 8-byte binary representation.
     *
     * @throws SnowflakeException
     */
    public static function toBytes(string $id): string {
        return self::encodeNumericBytes($id);
    }

    private static function assertDecodedId(string $id): string {
        if (!self::isValid($id)) {
            throw new SnowflakeException('Decoded Snowflake ID exceeds the supported signed domain');
        }

        return $id;
    }

    /**
     * @throws SnowflakeException
     */
    private static function assertNodeIds(int $datacenter, int $workerId): void {
        $maxDataCenter = -1 ^ (-1 << self::DATACENTER_BITS);
        $maxWorkId = -1 ^ (-1 << self::WORKER_BITS);

        if ($datacenter > $maxDataCenter || $datacenter < 0) {
            throw new SnowflakeException("Invalid datacenter ID, must be between 0 ~ $maxDataCenter.");
        }

        if ($workerId > $maxWorkId || $workerId < 0) {
            throw new SnowflakeException("Invalid worker ID, must be between 0 ~ $maxWorkId.");
        }
    }

    /**
     * @throws SnowflakeException
     */
    private static function assertTimestampRange(int $currentTime, int $startTimestamp): void {
        $elapsed = $currentTime - $startTimestamp;
        $maxTimestamp = -1 ^ (-1 << self::TIMESTAMP_BITS);
        if ($elapsed < 0) {
            throw new SnowflakeException('Snowflake epoch must not be in the future');
        }

        if ($elapsed > $maxTimestamp) {
            throw new SnowflakeException('Exceeding the maximum life cycle of the Snowflake algorithm');
        }
    }

    private static function decodeNumericBase(string $encoded, int $base): string {
        $id = NumericConversion::decimalFromBase(
            $encoded,
            $base,
            8,
            static fn(string $message, \InvalidArgumentException $exception): SnowflakeException => new SnowflakeException($message, 0, $exception),
        );

        return self::assertDecodedId($id);
    }

    private static function decodeNumericBytes(string $bytes): string {
        $id = NumericConversion::decimalFromBytes(
            $bytes,
            8,
            'Snowflake binary data must be exactly 8 bytes',
            static fn(string $message, \InvalidArgumentException $exception): SnowflakeException => new SnowflakeException($message, 0, $exception),
        );

        return self::assertDecodedId($id);
    }

    private static function encodeNumericBytes(string $id): string {
        return NumericConversion::bytesFromDecimal(
            $id,
            8,
            self::isValid(...),
            'Invalid Snowflake ID string',
            'Unable to convert Snowflake ID to bytes',
            static fn(string $message, \InvalidArgumentException $exception): SnowflakeException => new SnowflakeException($message, 0, $exception),
        );
    }

    /**
     * @throws SnowflakeException|FileLockException
     */
    private static function generateInternal(
        int $datacenter,
        int $workerId,
        int $startTimestamp,
        ClockBackwardPolicy $clockBackwardPolicy,
        ?SequenceProviderInterface $sequenceProvider = null,
        ?GenerationContext $runtime = null,
    ): string {
        self::assertNodeIds($datacenter, $workerId);

        $currentTime = self::nowMilliseconds($runtime);
        self::assertTimestampRange($currentTime, $startTimestamp);

        $resolvedSequenceProvider = self::resolveSequenceProvider($sequenceProvider);
        $sequenceKey = ($datacenter << self::WORKER_BITS) | $workerId;
        $stateKey = $startTimestamp . ':' . $sequenceKey;
        $providerState = self::providerState($resolvedSequenceProvider, $stateKey);
        $maxSequence = -1 ^ (-1 << self::SEQUENCE_BITS);
        $sequenceType = $startTimestamp === self::DEFAULT_EPOCH
            ? 'snowflake'
            : 'snowflake_' . $startTimestamp;

        while (true) {
            [$currentTime, $sequence] = self::nextSequenceAtValidTimestamp(
                $currentTime,
                $sequenceKey,
                $startTimestamp,
                $maxSequence,
                $clockBackwardPolicy,
                $resolvedSequenceProvider,
                $sequenceType,
                $runtime,
            );

            $lastState = $providerState[$stateKey] ?? null;

            if ($lastState === null) {
                break;
            }

            // Sequence backends can transiently return a smaller sequence for the same
            // timestamp under contention. Move forward and retry to preserve monotonic IDs.
            if (
                $currentTime < $lastState['timestamp']
                || ($currentTime === $lastState['timestamp'] && $sequence <= $lastState['sequence'])
            ) {
                $currentTime = self::waitUntil($lastState['timestamp'] + 1, $runtime);
                self::assertTimestampRange($currentTime, $startTimestamp);

                continue;
            }

            break;
        }

        $providerState[$stateKey] = [
            'timestamp' => $currentTime,
            'sequence' => $sequence,
        ];

        $workerLeftMoveLength = self::SEQUENCE_BITS;
        $datacenterLeftMoveLength = self::WORKER_BITS + $workerLeftMoveLength;
        $timestampLeftMoveLength = self::DATACENTER_BITS + $datacenterLeftMoveLength;

        return (string) ((($currentTime - $startTimestamp) << $timestampLeftMoveLength)
            | ($datacenter << $datacenterLeftMoveLength)
            | ($workerId << $workerLeftMoveLength)
            | ($sequence));
    }

    /**
     * Retrieves the start timestamp.
     */
    private static function getStartTimeStamp(): int {
        return self::DEFAULT_EPOCH;
    }

    /**
     * @return array{0:int, 1:int}
     * @throws FileLockException|SnowflakeException
     */
    private static function nextSequenceAtValidTimestamp(
        int $currentTime,
        int $sequenceKey,
        int $startTimestamp,
        int $maxSequence,
        ClockBackwardPolicy $clockBackwardPolicy,
        SequenceProviderInterface $sequenceProvider,
        string $sequenceType,
        ?GenerationContext $runtime,
    ): array {
        while (true) {
            try {
                $allocation = self::sequence($currentTime, $sequenceKey, $sequenceType, $sequenceProvider);
            } catch (SequenceTimestampException $exception) {
                if ($clockBackwardPolicy === ClockBackwardPolicy::THROW) {
                    throw new SnowflakeException(
                        'Clock moved backwards while generating Snowflake ID',
                        0,
                        $exception,
                    );
                }

                $currentTime = self::waitUntil($exception->lastTimestamp, $runtime);
                self::assertTimestampRange($currentTime, $startTimestamp);

                continue;
            }

            if ($allocation < 1) {
                throw new SnowflakeException('Snowflake sequence provider must return a positive allocation');
            }

            if ($allocation <= $maxSequence + 1) {
                return [$currentTime, $allocation - 1];
            }

            $currentTime = self::waitUntil($currentTime + 1, $runtime);
            self::assertTimestampRange($currentTime, $startTimestamp);
        }
    }

    private static function nowMilliseconds(?GenerationContext $runtime): int {
        return $runtime?->nowMilliseconds() ?? (int) floor(microtime(true) * 1000);
    }

    /**
     * @return \ArrayObject<string, array{timestamp:int, sequence:int}>
     */
    private static function providerState(
        SequenceProviderInterface $provider,
        string $stateKey,
    ): \ArrayObject {
        self::$lastStateByProvider ??= new \WeakMap();

        /** @var \ArrayObject<string, array{timestamp:int, sequence:int}>|null $state */
        $state = self::$lastStateByProvider[$provider] ?? null;
        if ($state === null) {
            /** @var \ArrayObject<string, array{timestamp:int, sequence:int}> $state */
            $state = new \ArrayObject();
            self::$lastStateByProvider[$provider] = $state;
        }

        if (!isset($state[$stateKey]) && count($state) >= self::MAX_PROVIDER_DOMAINS) {
            throw new SnowflakeException('Snowflake provider domain limit exceeded');
        }

        return $state;
    }

    private static function resolveSequenceProvider(?SequenceProviderInterface $provider): SequenceProviderInterface {
        return $provider ?? self::$sequenceProvider ??= new FilesystemSequenceProvider();
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function timestampParts(int $timestamp): array {
        return [(string) intdiv($timestamp, 1000), (string) (($timestamp % 1000) * 1000)];
    }

    private static function waitUntil(int $timestamp, ?GenerationContext $runtime): int {
        $deadline = $runtime?->waitDeadlineNanoseconds()
            ?? hrtime(true) + (self::WAIT_TIMEOUT_MICROS * 1_000);

        while (($now = self::nowMilliseconds($runtime)) < $timestamp) {
            if (hrtime(true) >= $deadline) {
                throw new SnowflakeException('Timed out waiting for a valid Snowflake timestamp');
            }

            if ($runtime !== null) {
                $runtime->sleepMicroseconds(1_000);
            } else {
                usleep(1_000);
            }
        }

        return $now;
    }
}
