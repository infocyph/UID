<?php

namespace Infocyph\UID;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use UnexpectedValueException;

class UUID
{
    private static array $nsList = [
        'dns' => 0,
        'url' => 1,
        'oid' => 2,
        'x500' => 4
    ];

    private static array $node = [];
    private static int $unixTs = 0;
    private static int $subSec = 0;
    private static int $secondIntervals = 10_000_000;
    private static int $secondIntervals78 = 10_000;
    private static int $timeOffset = 0x01b21dd213814000;

    /**
     * Generates a version 1 UUID.
     *
     * @param string|null $node The node identifier. Defaults to null.
     * @return string
     * @throws Exception
     */
    public static function v1(string $node = null): string
    {
        [$unixTs, $subSec] = self::getUnixTimeSubSec();
        $time = $unixTs . $subSec;
        $time = str_pad(dechex((int)$time + self::$timeOffset), 16, '0', STR_PAD_LEFT);
        $clockSeq = random_int(0, 0x3fff);
        return sprintf(
            '%08s-%04s-1%03s-%04x-%012s',
            substr($time, -8),
            substr($time, -12, 4),
            substr($time, -15, 3),
            $clockSeq | 0x8000,
            $node ?? self::getNode(1)
        );
    }

    /**
     * Generates the v3 UUID for a given string using the specified namespace.
     *
     * @param string $string The string to generate the hash for.
     * @param string $namespace The namespace to use for the hash generation.
     * @return string
     * @throws InvalidArgumentException
     */
    public static function v3(string $string, string $namespace): string
    {
        $namespace = self::nsResolve($namespace);
        if (!$namespace) {
            throw new InvalidArgumentException('Invalid NameSpace!');
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
     * @param string $string The string to generate the UUID from.
     * @param string $namespace The namespace to use for the UUID generation.
     * @return string
     * @throws InvalidArgumentException
     */
    public static function v5(string $string, string $namespace): string
    {
        $namespace = self::nsResolve($namespace);
        if (!$namespace) {
            throw new InvalidArgumentException('Invalid NameSpace!');
        }
        $hash = sha1(hex2bin($namespace) . $string);
        return self::output(5, $hash);
    }

    /**
     * Generates a Version 6 UUID.
     *
     * @param string|null $node The node identifier. Defaults to null.
     * @return string
     * @throws Exception
     */
    public static function v6(string $node = null): string
    {
        [$unixTs, $subSec] = self::getUnixTimeSubSec(6);
        $timestamp = $unixTs * self::$secondIntervals + $subSec;
        $timeHex = str_pad(dechex($timestamp + self::$timeOffset), 15, '0', STR_PAD_LEFT);
        $string = substr_replace(
                substr($timeHex, -15),
                '6',
                -3,
                0
            ) . ($node ?? self::getNode(6));
        return self::output(6, $string);
    }

    /**
     * Generates the v7 UUID for a given string using the specified namespace.
     *
     * @param string $string The random string to generate the UUID from.
     * @param string $namespace The namespace to use for the UUID generation.
     * @return string
     * @throws InvalidArgumentException
     */
    public static function v7(string $string, string $namespace): string
    {
        $namespace = self::nsResolve($namespace);
        if (!$namespace) {
            throw new InvalidArgumentException('Invalid NameSpace!');
        }
        $hash = hash('sha256',hex2bin($namespace) . $string);
        return self::output(7, $hash);
    }

    /**
     * Generates a version 8 UUID.
     *
     * @param string|null $node The node identifier. Defaults to null.
     * @return string
     * @throws Exception
     */
    public static function v8(string $node = null): string
    {
        [$unixTs, $subSec] = self::getUnixTimeSubSec(8);
        $unixTsMs = $unixTs * 1000 + intdiv($subSec, self::$secondIntervals78);
        $subSec = intdiv(($subSec % self::$secondIntervals78) << 14, self::$secondIntervals78);
        $subSecA = $subSec >> 2;
        $string = substr(str_pad(dechex($unixTsMs), 12, '0', STR_PAD_LEFT), -12) .
            '8' . str_pad(dechex($subSecA), 3, '0', STR_PAD_LEFT) .
            bin2hex(chr(ord(random_bytes(1)) & 0x0f | ($subSec & 0x03) << 4)) .
            ($node ?? self::getNode(8));
        return self::output(8, $string);
    }

    /**
     * Generate unique hexadecimal node.
     *
     * @param int $version The version of the UUID.
     * @return string The generated hexadecimal node.
     * @throws Exception
     */
    public static function getNode(int $version): string
    {
        return self::$node[$version] ??= bin2hex(
            match ($version) {
                1 => random_bytes(6),
                6 => random_bytes(8),
                8 => random_bytes(7),
            }
        );
    }

    /**
     * Retrieves the time from the UUID.
     *
     * @param string $uuid The UUID string to extract time from.
     * @return DateTimeInterface The DateTimeImmutable object representing the extracted time.
     * @throws InvalidArgumentException|Exception|UnexpectedValueException
     */
    public static function getTime(string $uuid): DateTimeInterface
    {
        if (!self::isValid($uuid)) {
            throw new InvalidArgumentException('Invalid UUID');
        }
        $uuid = str_getcsv($uuid, '-');
        $version = (int)$uuid[2][0];
        $timestamp = match ($version) {
            1 => substr($uuid[2], -3) . $uuid[1] . $uuid[0],
            6, 8 => $uuid[0] . $uuid[1] . substr($uuid[2], -3),
            default => throw new UnexpectedValueException('Invalid version')
        };

        if ($version == 8) {
            $unixTs = hexdec(substr('0' . $timestamp, 0, 13));
            $subSec = -(
                -(
                    (hexdec(substr('0' . $timestamp, 13)) << 2) +
                    (hexdec($uuid[3][0]) & 0x03)
                ) * self::$secondIntervals78 >> 14);
            $time = str_split(strval($unixTs * self::$secondIntervals78 + $subSec), 10);
            $time[1] = substr($time[1], 0, 6);
        } else {
            $timestamp = base_convert($timestamp, 16, 10);
            $epochNanoseconds = bcsub($timestamp, self::$timeOffset);
            $time = str_getcsv(bcdiv($epochNanoseconds, self::$secondIntervals, 6), '.');
        }

        return new DateTimeImmutable(
            '@'
            . $time[0]
            . '.'
            . str_pad($time[1], 6, '0', STR_PAD_LEFT)
        );
    }

    /**
     * Check if UUID is valid (validates version 1-9 & NIL)
     *
     * @param string $uuid The UUID to be checked
     * @return bool
     */
    public static function isValid(string $uuid): bool
    {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-\d[0-9a-f]{3}-[089ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    /**
     * Retrieves the Unix timestamp and sub-second component of the current time.
     *
     * @param int $version The version of the UUID. Defaults to 1.
     * @return array An array containing the Unix timestamp and sub-second component.
     */
    private static function getUnixTimeSubSec(int $version = 1): array
    {
        $timestamp = microtime();
        $unixTs = intval(substr($timestamp, 11));
        $subSec = intval(substr($timestamp, 2, 7));
        if ($version === 1) {
            return [$unixTs, $subSec];
        }
        if (self::$unixTs > $unixTs || self::$unixTs === $unixTs && self::$subSec >= $subSec) {
            $unixTs = self::$unixTs;
            $subSec = self::$subSec;
            if ($subSec >= self::$secondIntervals - 1) {
                $subSec = 0;
                $unixTs++;
            } else {
                $subSec++;
            }
        }
        self::$unixTs = $unixTs;
        self::$subSec = $subSec;
        return [$unixTs, $subSec];
    }

    /**
     * Generates a formatted string based on the given version and string.
     *
     * @param int $version The version number.
     * @param string $string The input string.
     * @return string The formatted string.
     */
    private static function output(int $version, string $string): string
    {
        $string = str_split($string, 4);
        return sprintf(
            "%08s-%04s-$version%03s-%04x-%012s",
            $string[0] . $string[1],
            $string[2],
            substr($string[3], 1, 3),
            hexdec($string[4]) & 0x3fff | 0x8000,
            $string[5] . $string[6] . $string[7]
        );
    }

    /**
     * Resolves the given namespace.
     *
     * @param string $namespace The namespace to be resolved.
     * @return string|array|bool The resolved namespace or false if it cannot be resolved.
     */
    private static function nsResolve(string $namespace): string|array|bool
    {
        if (self::isValid($namespace)) {
            return str_replace('-', '', $namespace);
        }
        $namespace = str_replace(['namespace', 'ns', '_'], '', strtolower($namespace));
        if (isset(self::$nsList[$namespace])) {
            return "6ba7b81" . self::$nsList[$namespace] . "9dad11d180b400c04fd430c8";
        }
        return false;
    }
}