<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Infocyph\UID\Exceptions\UUIDException;
use Infocyph\UID\Support\BaseEncoder;

use const STR_PAD_LEFT;

final class UUID
{
    /** @var array<string, int> */
    private static array $nsList = [
        'dns' => 0,
        'url' => 1,
        'oid' => 2,
        'x500' => 4,
    ];

    /** @var array<int, int> */
    private static array $randomLength = [
        6 => 2,
        7 => 4,
        8 => 1,
    ];

    private static int $secondIntervals = 10_000_000;

    private static int $secondIntervals78 = 10_000;

    /** @var array<int, int> */
    private static array $subSec = [
        1 => 0,
        6 => 0,
        7 => 0,
        8 => 0,
    ];

    private static int $timeOffset = 0x01b21dd213814000;

    /** @var array<int, int> */
    private static array $unixTs = [
        1 => 0,
        6 => 0,
        7 => 0,
        8 => 0,
    ];

    /** @var array{timestamp: int, tail: string}|null */
    private static ?array $v7DefaultState = null;

    /** @var array<string, array{timestamp: int, random: string}> */
    private static array $v7NodeState = [];

    /**
     * Converts a UUID to compact (32 hex chars, no dashes) format.
     *
     * @throws UUIDException
     */
    public static function compact(string $uuid): string
    {
        return self::normalizeInputToHex($uuid);
    }

    /**
     * Decodes one of bases: 16, 32, 36, 58, 62 into canonical UUID.
     *
     * @throws UUIDException
     */
    public static function fromBase(string $encoded, int $base): string
    {
        try {
            return self::fromBytes(BaseEncoder::decodeToBytes($encoded, $base, 16));
        } catch (\InvalidArgumentException $exception) {
            throw new UUIDException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Converts 16-byte binary UUID data to canonical UUID string.
     *
     * @throws UUIDException
     */
    public static function fromBytes(string $bytes): string
    {
        if (strlen($bytes) !== 16) {
            throw new UUIDException('UUID binary data must be exactly 16 bytes');
        }

        return self::canonicalFromHex(bin2hex($bytes));
    }

    /**
     * Generate unique node.
     *
     * @return string The generated node.
     * @throws Exception
     */
    public static function getNode(): string
    {
        return bin2hex(random_bytes(6));
    }

    /**
     * Generates a GUID (Globally Unique Identifier) string.
     *
     * @param bool $trim Whether to trim the curly braces from the GUID string. Default is true.
     * @return string The generated GUID string.
     * @throws Exception
     */
    public static function guid(bool $trim = true): string
    {
        if (function_exists('com_create_guid') === true) {
            $data = com_create_guid();
            if (!is_string($data)) {
                throw new UUIDException('Failed to generate GUID');
            }

            return $trim ? trim($data, '{}') : $data;
        }

        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $data = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return $trim ? $data : "\{$data\}";
    }

    /**
     * Checks whether the given UUID string is MAX.
     */
    public static function isMax(string $uuid): bool
    {
        try {
            return self::normalize($uuid) === self::max();
        } catch (UUIDException) {
            return false;
        }
    }

    /**
     * Checks whether the given UUID string is NIL.
     */
    public static function isNil(string $uuid): bool
    {
        try {
            return self::normalize($uuid) === self::nil();
        } catch (UUIDException) {
            return false;
        }
    }

    /**
     * Check if UUID is valid (validates version 1-9 & NIL)
     *
     * @param string $uuid The UUID to be checked
     */
    public static function isValid(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-\d[0-9a-f]{3}-[089a-e][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    /**
     * Converts a UUID to lowercase canonical format.
     *
     * @throws UUIDException
     */
    public static function lowercase(string $uuid): string
    {
        return strtolower(self::normalize($uuid));
    }

    /**
     * Returns the MAX UUID.
     */
    public static function max(): string
    {
        return 'ffffffff-ffff-ffff-ffff-ffffffffffff';
    }

    /**
     * Returns the NIL UUID.
     */
    public static function nil(): string
    {
        return '00000000-0000-0000-0000-000000000000';
    }

    /**
     * Normalizes a UUID to lowercase canonical format.
     *
     * @throws UUIDException
     */
    public static function normalize(string $uuid): string
    {
        return self::canonicalFromHex(self::normalizeInputToHex($uuid));
    }

    /**
     * Parses a UUID string and returns an array with information about the UUID.
     *
     * @param string $uuid The UUID string to parse.
     * @return array{isValid: bool, version: int|null, variant: string|null, time: DateTimeInterface|null, node: string|null, tail: string|null}
     * @throws Exception
     */
    public static function parse(string $uuid): array
    {
        $uuid = trim($uuid, '{}');
        $data = [
            'isValid' => self::isValid($uuid),
            'version' => null,
            'variant' => null,
            'time' => null,
            'node' => null,
            'tail' => null,
        ];

        if (!$data['isValid']) {
            return $data;
        }

        $uuidData = explode('-', $uuid);
        if (count($uuidData) !== 5) {
            return $data;
        }
        $variantN = hexdec($uuidData[3][0]);
        $data['version'] = (int) $uuidData[2][0];
        $data['time'] = in_array($data['version'], [1, 6, 7, 8]) ? self::getTime($uuidData, $data['version']) : null;
        $data['tail'] = $uuidData[4];
        $data['node'] = in_array($data['version'], [7, 8], true) ? null : $uuidData[4];
        $data['variant'] = match (true) {
            $variantN <= 7 => 'NCS',
            $variantN >= 8 && $variantN <= 11 => 'DCE 1.1, ISO/IEC 11578:1996',
            $variantN === 12 || $variantN === 13 => 'Microsoft GUID',
            $variantN === 14 => 'Reserved',
            default => 'Unknown',
        };

        return $data;
    }

    /**
     * Encodes UUID bytes into one of bases: 16, 32, 36, 58, 62.
     *
     * @throws UUIDException
     */
    public static function toBase(string $uuid, int $base): string
    {
        try {
            return BaseEncoder::encodeBytes(self::toBytes($uuid), $base);
        } catch (\InvalidArgumentException $exception) {
            throw new UUIDException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Converts a UUID to brace format.
     *
     * @throws UUIDException
     */
    public static function toBraces(string $uuid): string
    {
        return '{' . self::normalize($uuid) . '}';
    }

    /**
     * Converts a UUID string to 16-byte binary representation.
     *
     * @throws UUIDException
     */
    public static function toBytes(string $uuid): string
    {
        $bytes = hex2bin(self::normalizeInputToHex($uuid));
        $bytes !== false || throw new UUIDException('Unable to convert UUID to bytes');

        return $bytes;
    }

    /**
     * Converts a UUID to URN format.
     *
     * @throws UUIDException
     */
    public static function toUrn(string $uuid): string
    {
        return 'urn:uuid:' . self::normalize($uuid);
    }

    /**
     * Converts a UUID to uppercase canonical format.
     *
     * @throws UUIDException
     */
    public static function uppercase(string $uuid): string
    {
        return strtoupper(self::normalize($uuid));
    }

    /**
     * Generates a version 1 UUID.
     *
     * @param string|null $node The node identifier. Defaults to null.
     * @throws Exception
     */
    public static function v1(?string $node = null): string
    {
        [$unixTs, $subSec] = self::getUnixTimeSubSec();
        $time = str_pad(dechex((int) ($unixTs . $subSec) + self::$timeOffset), 16, '0', STR_PAD_LEFT);

        return sprintf(
            '%08s-%04s-1%03s-%04x-%012s',
            substr($time, -8),
            substr($time, -12, 4),
            substr($time, -15, 3),
            random_int(0, 0x3fff) & 0x3fff | 0x8000,
            $node ?? self::getNode(),
        );
    }

    /**
     * Generates the v3 UUID for a given string using the specified namespace.
     *
     * @param string $namespace The namespace to use for the hash generation.
     * @param string $string The string to generate the hash for.
     * @throws UUIDException
     */
    public static function v3(string $namespace, string $string): string
    {
        $namespace = self::nsResolve($namespace);
        if (!$namespace) {
            throw new UUIDException('Invalid NameSpace!');
        }
        $hash = md5(hex2bin($namespace) . $string);

        return self::output(3, $hash);
    }

    /**
     * Generates a version 4 UUID.
     *
     * @return string A version 4 UUID string.
     * @throws Exception
     */
    public static function v4(): string
    {
        $string = bin2hex(random_bytes(16));

        return self::output(4, $string);
    }

    /**
     * Generates the v3 UUID for a given string using the specified namespace.
     *
     * @param string $namespace The namespace to use for the hash generation.
     * @param string $string The string to generate the hash for.
     * @throws UUIDException
     */
    public static function v5(string $namespace, string $string): string
    {
        $namespace = self::nsResolve($namespace);
        if (!$namespace) {
            throw new UUIDException('Invalid NameSpace!');
        }
        $hash = sha1(hex2bin($namespace) . $string);

        return self::output(5, $hash);
    }

    /**
     * Generates a Version 6 UUID.
     *
     * @param string|null $node The node identifier. Defaults to null.
     * @throws Exception
     */
    public static function v6(?string $node = null): string
    {
        [$unixTs, $subSec] = self::getUnixTimeSubSec(6);
        $unixTs = (int) $unixTs;
        $subSec = (int) $subSec;
        $timestamp = $unixTs * self::$secondIntervals + $subSec;
        $timeHex = str_pad(dechex($timestamp + self::$timeOffset), 15, '0', STR_PAD_LEFT);
        $string = substr_replace(
            substr($timeHex, -15),
            '6',
            -3,
            0,
        ) . self::prepareNode(6, $node);

        return self::output(6, $string);
    }

    /**
     * Generates a version 7 UUID.
     *
     * @param DateTimeInterface|null $dateTime An optional DateTimeInterface object to create the UUID.
     * @param string|null $node The node identifier. Defaults to null.
     * @throws Exception
     */
    public static function v7(?DateTimeInterface $dateTime = null, ?string $node = null): string
    {
        $unixTsMs = (int) ($dateTime ?? new DateTimeImmutable('now'))->format('Uv');
        $isExplicitTimestamp = $dateTime !== null;

        if ($node === null) {
            [$unixTsMs, $tail] = self::nextV7DefaultState($unixTsMs, $isExplicitTimestamp);
        } else {
            [$unixTsMs, $randomPart] = self::nextV7NodeState($node, $unixTsMs, $isExplicitTimestamp);
            $tail = $randomPart . $node;
        }

        $string = substr(str_pad(dechex($unixTsMs), 12, '0', STR_PAD_LEFT), -12)
            . $tail;

        return self::output(7, $string);
    }

    /**
     * Generates a version 8 UUID.
     *
     * @param string|null $node The node identifier. Defaults to null.
     * @throws Exception
     */
    public static function v8(?string $node = null): string
    {
        [$unixTs, $subSec] = self::getUnixTimeSubSec(8);
        $unixTs = (int) $unixTs;
        $subSec = (int) $subSec;
        $unixTsMs = $unixTs * 1000 + intdiv($subSec, self::$secondIntervals78);
        $subSec = intdiv(($subSec % self::$secondIntervals78) << 14, self::$secondIntervals78);
        $subSecA = $subSec >> 2;
        $string = substr(str_pad(dechex($unixTsMs), 12, '0', STR_PAD_LEFT), -12)
            . '8' . str_pad(dechex($subSecA), 3, '0', STR_PAD_LEFT)
            . bin2hex(chr(ord(random_bytes(1)) & 0x0f | ($subSec & 0x03) << 4))
            . self::prepareNode(8, $node);

        return self::output(8, $string);
    }

    /**
     * Converts 32-char hex UUID data to canonical dashed format.
     */
    private static function canonicalFromHex(string $hex): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    /**
     * Retrieves the time from the UUID.
     *
     * @param array{0: string, 1: string, 2: string, 3: string, 4: string} $uuid The UUID array to extract time from.
     * @param int $version The version of the UUID.
     * @return DateTimeInterface The DateTimeImmutable object representing the extracted time.
     * @throws UUIDException|Exception
     */
    private static function getTime(array $uuid, int $version): DateTimeInterface
    {
        $timestamp = match ($version) {
            1 => substr((string) $uuid[2], -3) . $uuid[1] . $uuid[0],
            6, 8 => $uuid[0] . $uuid[1] . substr((string) $uuid[2], -3),
            7 => $uuid[0] . $uuid[1],
            default => throw new UUIDException('Invalid version (applicable: 1, 6, 7, 8)'),
        };

        switch ($version) {
            case 7:
                $unixTsMs = (int) hexdec($timestamp);
                $time = [
                    (string) intdiv($unixTsMs, 1000),
                    str_pad((string) (($unixTsMs % 1000) * 1000), 6, '0', STR_PAD_LEFT),
                ];

                break;
            case 8:
                $unixTs = hexdec(substr('0' . $timestamp, 0, 13));
                $subSec = -(
                    -(
                        (hexdec(substr('0' . $timestamp, 13)) << 2)
                        + (hexdec((string) $uuid[3][0]) & 0x03)
                    ) * self::$secondIntervals78 >> 14
                );
                $time = str_split((string) ($unixTs * self::$secondIntervals78 + $subSec), 10);
                $time[1] = substr($time[1], 0, 6);

                break;
            default:
                $timestamp = self::hexToDecimal($timestamp);
                $epochNanoseconds = bcsub($timestamp, (string) self::$timeOffset);
                $time = explode('.', bcdiv($epochNanoseconds, (string) self::$secondIntervals, 6));
        }

        return new DateTimeImmutable(
            '@'
            . $time[0]
            . '.'
            . str_pad($time[1], 6, '0', STR_PAD_LEFT),
        );
    }

    /**
     * Retrieves the Unix timestamp and sub-second component of the current time.
     *
     * @param int $version The version of the UUID. Defaults to 1.
     * @return array{0: int|string, 1: int|string} An array containing the Unix timestamp and sub-second component.
     */
    private static function getUnixTimeSubSec(int $version = 1): array
    {
        $timestamp = microtime();
        $unixTs = (int) substr($timestamp, 11);
        $subSec = (int) substr($timestamp, 2, 7);
        if ($version === 1) {
            return [(string) $unixTs, str_pad((string) $subSec, 7, '0', STR_PAD_LEFT)];
        }
        if (
            self::$unixTs[$version] > $unixTs
            || (self::$unixTs[$version] === $unixTs
                && self::$subSec[$version] >= $subSec)
        ) {
            $unixTs = self::$unixTs[$version];
            $subSec = self::$subSec[$version];
            if ($subSec >= self::$secondIntervals - 1) {
                $subSec = 0;
                $unixTs++;
            } else {
                $subSec++;
            }
        }
        self::$unixTs[$version] = $unixTs;
        self::$subSec[$version] = $subSec;

        return [$unixTs, $subSec];
    }

    /**
     * @return numeric-string
     */
    private static function hexToDecimal(string $hex): string
    {
        $decimal = '0';
        $hex = strtolower(ltrim($hex, '0'));
        if ($hex === '') {
            return '0';
        }

        foreach (str_split($hex) as $char) {
            $decimal = bcadd(
                bcmul($decimal, '16'),
                (string) hexdec($char),
            );
        }

        return $decimal;
    }

    /**
     * Increments a hexadecimal counter string by one.
     *
     * @return string|null The incremented value or null if overflow occurred.
     */
    private static function incrementHexCounter(string $hex): ?string
    {
        $chars = str_split(strtolower($hex));
        $hexChars = '0123456789abcdef';
        $nextNibbles = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];
        for ($index = count($chars) - 1; $index >= 0; --$index) {
            $value = strpos($hexChars, $chars[$index]);
            if ($value === false) {
                return null;
            }

            if ($value === 15) {
                $chars[$index] = '0';

                continue;
            }

            $nextNibble = $nextNibbles[$value] ?? null;
            if ($nextNibble === null) {
                return null;
            }
            $chars[$index] = $nextNibble;

            return implode('', $chars);
        }

        return null;
    }

    /**
     * @return array{0: int, 1: string}
     * @throws Exception
     */
    private static function nextV7DefaultState(int $unixTsMs, bool $isExplicitTimestamp): array
    {
        $state = self::$v7DefaultState;

        if ($state === null || $unixTsMs > $state['timestamp']) {
            $tail = self::randomV7Tail();
            self::$v7DefaultState = ['timestamp' => $unixTsMs, 'tail' => $tail];

            return [$unixTsMs, $tail];
        }

        if ($isExplicitTimestamp && $state['timestamp'] !== $unixTsMs) {
            $tail = self::randomV7Tail();
            self::$v7DefaultState = ['timestamp' => $unixTsMs, 'tail' => $tail];

            return [$unixTsMs, $tail];
        }

        $unixTsMs = $state['timestamp'];
        $tail = self::incrementHexCounter($state['tail']);
        if ($tail === null) {
            if ($isExplicitTimestamp) {
                throw new UUIDException('Monotonic UUID v7 overflow for the provided timestamp');
            }

            $unixTsMs = self::nextV7Timestamp($state['timestamp']);
            $tail = self::randomV7Tail();
        }

        self::$v7DefaultState = ['timestamp' => $unixTsMs, 'tail' => $tail];

        return [$unixTsMs, $tail];
    }

    /**
     * @return array{0: int, 1: string}
     * @throws Exception
     */
    private static function nextV7NodeState(string $node, int $unixTsMs, bool $isExplicitTimestamp): array
    {
        $stateKey = 'node:' . $node;
        $state = self::$v7NodeState[$stateKey] ?? null;

        if ($state === null || $unixTsMs > $state['timestamp']) {
            $randomPart = self::randomV7NodePart();
            self::$v7NodeState[$stateKey] = ['timestamp' => $unixTsMs, 'random' => $randomPart];

            return [$unixTsMs, $randomPart];
        }

        if ($isExplicitTimestamp && $state['timestamp'] !== $unixTsMs) {
            $randomPart = self::randomV7NodePart();
            self::$v7NodeState[$stateKey] = ['timestamp' => $unixTsMs, 'random' => $randomPart];

            return [$unixTsMs, $randomPart];
        }

        $unixTsMs = $state['timestamp'];
        $randomPart = self::incrementHexCounter($state['random']);
        if ($randomPart === null) {
            if ($isExplicitTimestamp) {
                throw new UUIDException('Monotonic UUID v7 overflow for the provided timestamp');
            }

            $unixTsMs = self::nextV7Timestamp($state['timestamp']);
            $randomPart = self::randomV7NodePart();
        }

        self::$v7NodeState[$stateKey] = ['timestamp' => $unixTsMs, 'random' => $randomPart];

        return [$unixTsMs, $randomPart];
    }

    /**
     * Waits until the system clock moves to the next millisecond.
     */
    private static function nextV7Timestamp(int $lastTimestamp): int
    {
        do {
            usleep(1000);
            $next = (int) (new DateTimeImmutable('now'))->format('Uv');
        } while ($next <= $lastTimestamp);

        return $next;
    }

    /**
     * Normalizes possible UUID input variants to 32-char lowercase hex.
     *
     * @throws UUIDException
     */
    private static function normalizeInputToHex(string $uuid): string
    {
        $uuid = trim($uuid);
        if (stripos($uuid, 'urn:uuid:') === 0) {
            $uuid = substr($uuid, 9);
        }

        $uuid = trim($uuid, '{}');
        $hex = str_replace('-', '', $uuid);

        if (!preg_match('/^[0-9a-f]{32}$/i', $hex)) {
            throw new UUIDException('Invalid UUID format');
        }

        return strtolower($hex);
    }

    /**
     * Resolves the given namespace.
     *
     * @param string $namespace The namespace to be resolved.
     * @return string The resolved namespace or false if it cannot be resolved.
     */
    private static function nsResolve(string $namespace): string
    {
        if (self::isValid($namespace)) {
            return str_replace('-', '', $namespace);
        }
        $namespace = str_replace(['namespace', 'ns', '_'], '', strtolower($namespace));
        if (isset(self::$nsList[$namespace])) {
            return '6ba7b81' . self::$nsList[$namespace] . '9dad11d180b400c04fd430c8';
        }

        return '';
    }

    /**
     * Generates a formatted string based on the given version and string.
     *
     * @param int $version The version number.
     * @param string $id The string to be formatted.
     * @return string The formatted string.
     */
    private static function output(int $version, string $id): string
    {
        $string = str_split($id, 4);

        return sprintf(
            "%08s-%04s-$version%03s-%04x-%012s",
            $string[0] . $string[1],
            $string[2],
            substr($string[3], 1, 3),
            hexdec($string[4]) & 0x3fff | 0x8000,
            $string[5] . $string[6] . $string[7],
        );
    }

    /**
     * Generates a random node string based on the given version and node.
     *
     * @param int $version The version of the node.
     * @param string|null $node The node identifier. Defaults to null.
     * @return string The generated node string.
     * @throws Exception
     */
    private static function prepareNode(int $version, ?string $node = null): string
    {
        if (!$node) {
            return bin2hex(random_bytes(self::randomLengthFor($version) + 6));
        }

        return bin2hex(random_bytes(self::randomLengthFor($version))) . $node;
    }

    /**
     * @return int<1, max>
     */
    private static function randomLengthFor(int $version): int
    {
        $length = self::$randomLength[$version] ?? throw new UUIDException('Unsupported UUID version for random length');
        if ($length < 1) {
            throw new UUIDException('Random length must be greater than zero');
        }

        return $length;
    }

    /**
     * @throws Exception
     */
    private static function randomV7NodePart(): string
    {
        return bin2hex(random_bytes(self::randomLengthFor(7)));
    }

    /**
     * @throws Exception
     */
    private static function randomV7Tail(): string
    {
        return bin2hex(random_bytes(self::randomLengthFor(7) + 6));
    }
}
