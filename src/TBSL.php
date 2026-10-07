<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Exceptions\SequenceTimestampException;
use Infocyph\UID\Exceptions\UIDException;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\GetSequence;

final class TBSL
{
    use GetSequence;

    private const int WAIT_TIMEOUT_MICROS = 1_000_000;

    /** @var \WeakMap<SequenceProviderInterface, \ArrayObject<int, int>>|null */
    private static ?\WeakMap $lastTimeByProvider = null;

    /**
     * Decodes one of bases: 16, 32, 36, 58, 62 into canonical TBSL.
     *
     * @throws Exception
     */
    public static function fromBase(string $encoded, int $base): string
    {
        return self::fromBytes(BaseEncoder::decodeToBytes($encoded, $base, 10));
    }

    /**
     * Converts 10-byte TBSL binary data to uppercase TBSL string.
     *
     * @throws Exception
     */
    public static function fromBytes(string $bytes): string
    {
        if (strlen($bytes) !== 10) {
            throw new Exception('TBSL binary data must be exactly 10 bytes');
        }

        return strtoupper(bin2hex($bytes));
    }

    /**
     * Generates a unique identifier using the TBSL algorithm.
     *
     * @param int $machineId 2-digit (0-99) machine identifier. Default is 0.
     * @param bool $sequenced Whether to use sequencing.
     * @return string The generated unique identifier.
     * @throws Exception
     */
    public static function generate(int $machineId = 0, bool $sequenced = true): string
    {
        return self::generateInternal(
            $machineId,
            $sequenced,
            ClockBackwardPolicy::WAIT,
        );
    }

    /**
     * Generates TBSL using configuration object.
     *
     * @throws Exception
     */
    public static function generateRandom(int $machineId = 0): string
    {
        return self::generate($machineId, false);
    }

    public static function generateWithConfig(TBSLConfig $config): string
    {
        return self::generateInternal(
            $config->resolveMachineId(),
            $config->sequenced,
            $config->clockBackwardPolicy,
            $config->sequenceProvider,
            $config->runtime,
        );
    }

    /**
     * Checks whether a TBSL string is valid.
     */
    public static function isValid(string $tbsl): bool
    {
        return (bool) preg_match('/^[0-9A-F]{20}$/D', $tbsl);
    }

    /**
     * Parses a TBSL string and returns an array with its components.
     *
     * @param string $tbsl The TBSL string to parse.
     * @return array{time: DateTimeImmutable, machineId: int}
     * @throws Exception
     */
    public static function parse(string $tbsl): array
    {
        if (!self::isValid($tbsl)) {
            throw new UIDException('Invalid TBSL string');
        }

        $storeBytes = hex2bin('0' . substr($tbsl, 0, 15));
        $storeBytes !== false || throw new Exception('Unable to parse TBSL timestamp');
        $storeParts = unpack('Jvalue', $storeBytes);
        $storeValue = $storeParts['value'] ?? null;
        is_int($storeValue) || throw new Exception('Unable to parse TBSL timestamp');
        $time = intdiv($storeValue, 100);

        return [
            'time' => new DateTimeImmutable('@' . intdiv($time, 1_000_000) . '.' . str_pad((string) ($time % 1_000_000), 6, '0', STR_PAD_LEFT)),
            'machineId' => $storeValue % 100,
        ];
    }

    /**
     * Encodes TBSL bytes into one of bases: 16, 32, 36, 58, 62.
     *
     * @throws Exception
     */
    public static function toBase(string $tbsl, int $base): string
    {
        return BaseEncoder::encodeBytes(self::toBytes($tbsl), $base);
    }

    /**
     * Converts a TBSL string to 10-byte binary representation.
     *
     * @throws Exception
     */
    public static function toBytes(string $tbsl): string
    {
        if (!self::isValid($tbsl)) {
            throw new Exception('Invalid TBSL string');
        }

        $bytes = hex2bin($tbsl);
        $bytes !== false || throw new Exception('Unable to convert TBSL to bytes');

        return $bytes;
    }

    /**
     * @throws UIDException
     */
    private static function assertMachineId(int $machineId): void
    {
        if ($machineId < 0 || $machineId > 99) {
            throw new UIDException('Invalid machine ID, must be between 0 and 99');
        }
    }

    private static function assertTimestamp(int $timestamp, int $machineId): void
    {
        if ($timestamp < 0 || $timestamp > intdiv(0x0fffffffffffffff - $machineId, 100)) {
            throw new UIDException('TBSL timestamp exceeds its 60-bit field');
        }
    }

    /**
     * @throws Exception
     */
    private static function generateInternal(
        int $machineId,
        bool $sequenced,
        ClockBackwardPolicy $clockBackwardPolicy,
        ?SequenceProviderInterface $sequenceProvider = null,
        ?GenerationContext $runtime = null,
    ): string {
        self::assertMachineId($machineId);

        $sequenceProvider ??= self::$sequenceProvider ??= new FilesystemSequenceProvider();
        self::$lastTimeByProvider ??= new \WeakMap();
        /** @var \ArrayObject<int, int> $state */
        $state = self::$lastTimeByProvider[$sequenceProvider] ??= new \ArrayObject();
        $lastTime = $state[$machineId] ?? 0;
        $timeSequence = self::nowMicroseconds($runtime);

        if ($timeSequence < $lastTime) {
            if ($clockBackwardPolicy === ClockBackwardPolicy::THROW) {
                throw new UIDException('Clock moved backwards while generating TBSL ID');
            }

            $timeSequence = self::waitUntilNextTimeSequence($lastTime, $runtime);
        }
        self::assertTimestamp($timeSequence, $machineId);
        [$timeSequence, $tail] = self::resolveTail(
            $machineId,
            $sequenced,
            $timeSequence,
            $clockBackwardPolicy,
            $sequenceProvider,
            $runtime,
        );
        self::assertTimestamp($timeSequence, $machineId);
        $state[$machineId] = max($state[$machineId] ?? 0, $timeSequence);

        $storeValue = ($timeSequence * 100) + $machineId;
        $storeData = ltrim(bin2hex(pack('J', $storeValue)), '0');
        if (strlen($storeData) > 15) {
            throw new UIDException('TBSL timestamp exceeds its 60-bit field');
        }

        return strtoupper(sprintf(
            '%015s%05s',
            $storeData,
            $tail,
        ));
    }

    private static function nowMicroseconds(?GenerationContext $runtime): int
    {
        return $runtime?->nowMicroseconds() ?? (int) floor(microtime(true) * 1_000_000);
    }

    /**
     * Generates a sequence or random bytes based on the sequencing flag.
     *
     * @param int $machineId Machine identifier.
     * @param bool $enableSequence Whether to enable sequence.
     * @param int $timeSequence The timestamp sequence.
     * @return array{0:int, 1:string}
     * @throws Exception
     */
    private static function resolveTail(
        int $machineId,
        bool $enableSequence,
        int $timeSequence,
        ClockBackwardPolicy $clockBackwardPolicy,
        SequenceProviderInterface $sequenceProvider,
        ?GenerationContext $runtime = null,
    ): array {
        if (!$enableSequence) {
            return [$timeSequence, substr(bin2hex(random_bytes(3)), 0, 5)];
        }

        do {
            try {
                $sequence = self::sequence($timeSequence, $machineId, 'tbsl', $sequenceProvider, $runtime);
            } catch (SequenceTimestampException $exception) {
                if ($clockBackwardPolicy === ClockBackwardPolicy::THROW) {
                    throw new UIDException(
                        'Clock moved backwards while generating TBSL ID',
                        0,
                        $exception,
                    );
                }

                $timeSequence = self::waitUntilNextTimeSequence($exception->lastTimestamp, $runtime);

                continue;
            }

            if ($sequence < 1) {
                throw new UIDException('TBSL sequence provider must return a positive integer');
            }

            if ($sequence <= 0x100000) {
                return [$timeSequence, str_pad(dechex($sequence - 1), 5, '0', STR_PAD_LEFT)];
            }

            $timeSequence = self::waitUntilNextTimeSequence($timeSequence, $runtime);
        } while (true);
    }

    private static function waitUntilNextTimeSequence(int $last, ?GenerationContext $runtime): int
    {
        $deadline = $runtime?->waitDeadlineNanoseconds()
            ?? hrtime(true) + (self::WAIT_TIMEOUT_MICROS * 1_000);

        while (($candidate = self::nowMicroseconds($runtime)) <= $last) {
            if (hrtime(true) >= $deadline) {
                throw new UIDException('Timed out waiting for the next TBSL timestamp');
            }

            if ($runtime !== null) {
                $runtime->sleepMicroseconds(100);
            } else {
                usleep(100);
            }
        }

        return $candidate;
    }
}
