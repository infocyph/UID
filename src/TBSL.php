<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Exceptions\UIDException;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\DecimalBytes;
use Infocyph\UID\Support\GetSequence;
use Infocyph\UID\Support\UnsignedDecimal;

final class TBSL
{
    use GetSequence;

    private static int $lastTimeSequence = 0;

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
    public static function generate(int $machineId = 0, bool $sequenced = false): string
    {
        return (string) self::generateInternal(
            $machineId,
            $sequenced,
            ClockBackwardPolicy::WAIT,
            IdOutputType::STRING,
        );
    }

    /**
     * Generates TBSL using configuration object.
     *
     * @throws Exception
     */
    public static function generateWithConfig(TBSLConfig $config): int|string
    {
        return self::generateInternal(
            $config->resolveMachineId(),
            $config->sequenced,
            $config->clockBackwardPolicy,
            $config->outputType,
            $config->sequenceProvider,
        );
    }

    /**
     * Checks whether a TBSL string is valid.
     */
    public static function isValid(string $tbsl): bool
    {
        return (bool) preg_match('/^[0-9A-F]{20}$/', $tbsl);
    }

    /**
     * Parses a TBSL string and returns an array with its components.
     *
     * @param string $tbsl The TBSL string to parse.
     * @return array{isValid: bool, time: DateTimeImmutable|null, machineId: int|null}
     * @throws Exception
     */
    public static function parse(string $tbsl): array
    {
        $data = [
            'isValid' => self::isValid($tbsl),
            'time' => null,
            'machineId' => null,
        ];

        if (!$data['isValid']) {
            return $data;
        }

        $storeData = base_convert(substr($tbsl, 0, 15), 16, 10);
        $data['time'] = new DateTimeImmutable('@' . substr($storeData, 0, 10) . '.' . substr($storeData, 10, 6));
        $data['machineId'] = (int) substr($storeData, -2);

        return $data;
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

    private static function formatOutput(string $id, IdOutputType $outputType): int|string
    {
        return match ($outputType) {
            IdOutputType::STRING => $id,
            IdOutputType::BINARY => self::toBytes($id),
            IdOutputType::INT => self::hexToDecimal($id),
        };
    }

    /**
     * @throws Exception
     */
    private static function generateInternal(
        int $machineId,
        bool $sequenced,
        ClockBackwardPolicy $clockBackwardPolicy,
        IdOutputType $outputType,
        ?SequenceProviderInterface $sequenceProvider = null,
    ): int|string {
        self::assertMachineId($machineId);

        [$micro, $seconds] = explode(' ', microtime());
        $timeSequence = (int) ($seconds . substr($micro, 2, 6));

        if ($timeSequence < self::$lastTimeSequence) {
            if ($clockBackwardPolicy === ClockBackwardPolicy::THROW) {
                throw new UIDException('Clock moved backwards while generating TBSL ID');
            }

            $timeSequence = self::waitUntilNextTimeSequence(self::$lastTimeSequence);
        }
        self::$lastTimeSequence = $timeSequence;

        $storeData = base_convert($timeSequence . sprintf('%02d', $machineId), 10, 16);
        $id = strtoupper(sprintf(
            '%015s%05s',
            $storeData,
            substr(self::sequencedGenerate($machineId, $sequenced, $timeSequence, $sequenceProvider), 0, 5),
        ));

        return self::formatOutput($id, $outputType);
    }

    private static function hexToDecimal(string $hex): int
    {
        $bytes = hex2bin(strtolower($hex));
        $bytes !== false || throw new UIDException('Unable to convert TBSL hex to bytes');
        $decimal = DecimalBytes::fromBytes($bytes);

        if (UnsignedDecimal::compare($decimal, (string) PHP_INT_MAX) === 1) {
            throw new UIDException('TBSL integer output exceeds PHP_INT_MAX; use string or binary output');
        }

        return (int) $decimal;
    }

    /**
     * Generates a sequence or random bytes based on the sequencing flag.
     *
     * @param int $machineId Machine identifier.
     * @param bool $enableSequence Whether to enable sequence.
     * @param int $timeSequence The timestamp sequence.
     * @return string Hexadecimal sequence.
     * @throws Exception
     */
    private static function sequencedGenerate(
        int $machineId,
        bool $enableSequence,
        int $timeSequence,
        ?SequenceProviderInterface $sequenceProvider = null,
    ): string {
        return match ($enableSequence) {
            true => dechex(self::sequence($timeSequence, $machineId, 'tbsl', $sequenceProvider)),
            default => bin2hex(random_bytes(3)),
        };
    }

    private static function waitUntilNextTimeSequence(int $last): int
    {
        do {
            [$micro, $seconds] = explode(' ', microtime());
            $candidate = (int) ($seconds . substr($micro, 2, 6));
        } while ($candidate <= $last);

        return $candidate;
    }
}
