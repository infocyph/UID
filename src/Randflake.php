<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Configuration\RandflakeConfig;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\RandflakeException;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\DecimalBytes;
use Infocyph\UID\Support\GetSequence;
use Infocyph\UID\Support\NumericConversion;
use Infocyph\UID\Support\OutputFormatter;

final class Randflake
{
    use GetSequence;

    public const EPOCH_OFFSET = 1_730_000_000;

    public const MAX_NODE = (1 << self::NODE_BITS) - 1;

    public const MAX_SEQUENCE = (1 << self::SEQUENCE_BITS) - 1;

    public const MAX_TIMESTAMP = self::EPOCH_OFFSET + self::MAX_TIMESTAMP_PART;

    public const MAX_TIMESTAMP_PART = (1 << self::TIMESTAMP_BITS) - 1;

    public const NODE_BITS = 17;

    public const SEQUENCE_BITS = 17;

    public const TIMESTAMP_BITS = 30;

    /**
     * @var array<string, int>
     */
    private static array $lastTimestampByNode = [];

    /**
     * @throws RandflakeException
     */
    public static function decodeString(string $id): string
    {
        return NumericConversion::decimalFromBase(
            $id,
            32,
            8,
            static fn(string $message, \InvalidArgumentException $exception): RandflakeException => new RandflakeException(
                $message === '' ? 'randflake: invalid id' : 'randflake: invalid id',
                0,
                $exception,
            ),
        );
    }

    /**
     * @throws RandflakeException
     */
    public static function encodeString(string $id): string
    {
        if (!self::isValid($id)) {
            throw new RandflakeException('randflake: invalid id');
        }

        return BaseEncoder::encodeBytes(self::toBytes($id), 32);
    }

    /**
     * @throws RandflakeException
     */
    public static function fromBase(string $encoded, int $base): string
    {
        return NumericConversion::decimalFromBase(
            $encoded,
            $base,
            8,
            static fn(string $message, \InvalidArgumentException $exception): RandflakeException => new RandflakeException($message, 0, $exception),
        );
    }

    /**
     * @throws RandflakeException
     */
    public static function fromBytes(string $bytes): string
    {
        return NumericConversion::decimalFromBytes(
            $bytes,
            8,
            'randflake: invalid id',
            static fn(string $message, \InvalidArgumentException $exception): RandflakeException => new RandflakeException($message, 0, $exception),
        );
    }

    /**
     * @throws RandflakeException|FileLockException
     */
    public static function generate(int $nodeId, int $leaseStart, int $leaseEnd, string $secret): string
    {
        return (string) self::generateInternal(
            $nodeId,
            $leaseStart,
            $leaseEnd,
            $secret,
            IdOutputType::STRING,
            null,
        );
    }

    /**
     * @throws RandflakeException|FileLockException
     */
    public static function generateString(int $nodeId, int $leaseStart, int $leaseEnd, string $secret): string
    {
        return self::encodeString(self::generate($nodeId, $leaseStart, $leaseEnd, $secret));
    }

    /**
     * @throws RandflakeException|FileLockException
     */
    public static function generateWithConfig(RandflakeConfig $config): int|string
    {
        return self::generateInternal(
            $config->nodeId,
            $config->leaseStart,
            $config->leaseEnd,
            $config->secret,
            $config->outputType,
            $config->sequenceProvider,
        );
    }

    /**
     * @return array{timestamp: int, node_id: int, sequence: int}
     * @throws RandflakeException
     */
    public static function inspect(string $id, string $secret): array
    {
        if (!self::isValid($id)) {
            throw new RandflakeException('randflake: invalid id');
        }

        [$timestamp, $nodeId, $sequence] = self::inspectBytes(
            self::toBytes($id),
            self::validateSecret($secret),
        );

        return [
            'timestamp' => $timestamp,
            'node_id' => $nodeId,
            'sequence' => $sequence,
        ];
    }

    /**
     * @return array{timestamp: int, node_id: int, sequence: int}
     * @throws RandflakeException
     */
    public static function inspectString(string $id, string $secret): array
    {
        return self::inspect(self::decodeString($id), $secret);
    }

    public static function isValid(string $id): bool
    {
        return $id !== '' && ctype_digit($id);
    }

    /**
     * @return array{time: DateTimeImmutable, node_id: int, sequence: int}
     * @throws Exception
     */
    public static function parse(string $id, string $secret): array
    {
        if (!self::isValid($id)) {
            throw new RandflakeException('randflake: invalid id');
        }

        [$timestamp, $nodeId, $sequence] = self::inspectBytes(
            self::toBytes($id),
            self::validateSecret($secret),
        );

        return [
            'time' => new DateTimeImmutable('@' . $timestamp),
            'node_id' => $nodeId,
            'sequence' => $sequence,
        ];
    }

    /**
     * @return array{time: DateTimeImmutable, node_id: int, sequence: int}
     * @throws Exception
     */
    public static function parseString(string $id, string $secret): array
    {
        return self::parse(self::decodeString($id), $secret);
    }

    /**
     * @throws RandflakeException
     */
    public static function toBase(string $id, int $base): string
    {
        return BaseEncoder::encodeBytes(self::toBytes($id), $base);
    }

    /**
     * @throws RandflakeException
     */
    public static function toBytes(string $id): string
    {
        return NumericConversion::bytesFromDecimal(
            $id,
            8,
            self::isValid(...),
            'randflake: invalid id',
            'randflake: invalid id',
            static fn(string $message, \InvalidArgumentException $exception): RandflakeException => new RandflakeException($message, 0, $exception),
        );
    }

    /**
     * @throws RandflakeException|FileLockException
     */
    private static function generateInternal(
        int $nodeId,
        int $leaseStart,
        int $leaseEnd,
        string $secret,
        IdOutputType $outputType,
        ?SequenceProviderInterface $sequenceProvider,
    ): int|string {
        self::validateNode($nodeId);
        self::validateLeaseWindow($leaseStart, $leaseEnd);
        $secret = self::validateSecret($secret);

        $resolvedSequenceProvider = self::resolveSequenceProvider($sequenceProvider);
        $stateKey = spl_object_id($resolvedSequenceProvider) . ':' . $nodeId;
        $now = time();
        if ($now < $leaseStart || $now > $leaseEnd) {
            throw new RandflakeException('randflake: invalid lease, lease expired or not started yet');
        }

        if ($now > self::MAX_TIMESTAMP) {
            throw new RandflakeException('randflake: the randflake id is dead after 34 years of lifetime');
        }

        $lastTimestamp = self::$lastTimestampByNode[$stateKey] ?? null;
        if ($lastTimestamp !== null && $now < $lastTimestamp) {
            throw new RandflakeException('randflake: timestamp consistency violation, the current time is less than the last time');
        }

        $sequence = self::sequence($now, $nodeId, 'randflake', $resolvedSequenceProvider) - 1;
        if ($sequence > self::MAX_SEQUENCE) {
            throw new RandflakeException(
                "randflake: resource exhausted (generator can't handle current throughput, try using multiple randflake instances)",
            );
        }

        self::$lastTimestampByNode[$stateKey] = $now;

        $plain = self::packPayload($now, $nodeId, $sequence);
        $cipher = self::permute($plain, $secret, false);
        $decimalId = DecimalBytes::fromBytes($cipher);

        return OutputFormatter::formatNumeric($decimalId, $outputType);
    }

    /**
     * @return array{0:int,1:int,2:int}
     * @throws RandflakeException
     */
    private static function inspectBytes(string $cipherBytes, string $secret): array
    {
        $plain = self::permute($cipherBytes, $secret, true);
        [$timestamp, $nodeId, $sequence] = self::unpackPayload($plain);

        if (
            $timestamp < self::EPOCH_OFFSET
            || $timestamp > self::MAX_TIMESTAMP
            || $nodeId < 0
            || $nodeId > self::MAX_NODE
            || $sequence < 0
            || $sequence > self::MAX_SEQUENCE
        ) {
            throw new RandflakeException('randflake: invalid id');
        }

        return [$timestamp, $nodeId, $sequence];
    }

    private static function packPayload(int $timestamp, int $nodeId, int $sequence): string
    {
        $timestampPart = $timestamp - self::EPOCH_OFFSET;
        $high = (($timestampPart & self::MAX_TIMESTAMP_PART) << 2) | (($nodeId >> 15) & 0x03);
        $low = (($nodeId & 0x7fff) << 17) | ($sequence & self::MAX_SEQUENCE);

        return pack('N2', $high, $low);
    }

    /**
     * Small secret-key permutation over 64-bit blocks to protect payload fields.
     */
    private static function permute(string $block, string $secret, bool $decrypt): string
    {
        $parts = unpack('Nleft/Nright', $block);
        $left = self::unpackedInt($parts, 'left');
        $right = self::unpackedInt($parts, 'right');
        $roundKeys = self::roundKeys($secret);
        $mask = 0xffffffff;

        if ($decrypt) {
            for ($round = count($roundKeys) - 1; $round >= 0; --$round) {
                $nextRight = $left;
                $nextLeft = ($right ^ self::roundFunction($left, $roundKeys[$round])) & $mask;
                $left = $nextLeft;
                $right = $nextRight;
            }
        } else {
            foreach ($roundKeys as $roundKey) {
                $nextLeft = $right;
                $nextRight = ($left ^ self::roundFunction($right, $roundKey)) & $mask;
                $left = $nextLeft;
                $right = $nextRight;
            }
        }

        return pack('N2', $left, $right);
    }

    private static function resolveSequenceProvider(?SequenceProviderInterface $provider): SequenceProviderInterface
    {
        return $provider ?? self::$sequenceProvider ??= new FilesystemSequenceProvider();
    }

    private static function roundFunction(int $value, int $key): int
    {
        $mask = 0xffffffff;
        $value &= $mask;
        $leftRot = (($value << 5) | ($value >> 27)) & $mask;
        $rightRot = (($value >> 3) | ($value << 29)) & $mask;
        $mixed = ($leftRot + $key) & $mask;
        $mixed ^= $rightRot;
        $mixed ^= 0x9e3779b9;

        return $mixed & $mask;
    }

    /**
     * @return array<int, int>
     */
    private static function roundKeys(string $secret): array
    {
        $keys = [];
        for ($round = 0; $round < 8; ++$round) {
            $material = hash('sha256', $secret . ':' . $round, true);
            $parts = unpack('Nkey', substr($material, 0, 4));
            $keys[] = self::unpackedInt($parts, 'key');
        }

        return $keys;
    }

    /**
     * @param array<array-key, mixed>|false $parts
     * @throws RandflakeException
     */
    private static function unpackedInt(array|false $parts, string $key): int
    {
        if ($parts === false) {
            throw new RandflakeException('randflake: invalid id');
        }

        $value = $parts[$key] ?? null;
        if (!is_int($value)) {
            throw new RandflakeException('randflake: invalid id');
        }

        return $value;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function unpackPayload(string $payload): array
    {
        $parts = unpack('Nhigh/Nlow', $payload);
        $high = self::unpackedInt($parts, 'high');
        $low = self::unpackedInt($parts, 'low');

        $timestampPart = ($high >> 2) & self::MAX_TIMESTAMP_PART;
        $nodeId = (($high & 0x03) << 15) | (($low >> 17) & 0x7fff);
        $sequence = $low & self::MAX_SEQUENCE;

        return [$timestampPart + self::EPOCH_OFFSET, $nodeId, $sequence];
    }

    /**
     * @throws RandflakeException
     */
    private static function validateLeaseWindow(int $leaseStart, int $leaseEnd): void
    {
        if ($leaseStart > $leaseEnd || $leaseEnd > self::MAX_TIMESTAMP) {
            throw new RandflakeException('randflake: invalid lease, lease expired or not started yet');
        }
    }

    /**
     * @throws RandflakeException
     */
    private static function validateNode(int $nodeId): void
    {
        if ($nodeId < 0 || $nodeId > self::MAX_NODE) {
            throw new RandflakeException('randflake: invalid node id, node id must be between 0 and 131071');
        }
    }

    /**
     * @throws RandflakeException
     */
    private static function validateSecret(string $secret): string
    {
        if (strlen($secret) !== 16) {
            throw new RandflakeException('randflake: invalid secret, secret must be 16 bytes long');
        }

        return $secret;
    }
}
