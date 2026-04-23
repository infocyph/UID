<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

final class RandomId
{
    private static string $cuid2Alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';

    private static int $cuid2counter;

    /**
     * Generates a CUID-2 string with a specified maximum length.
     *
     * @param int $maxLength The maximum length of the CUID-2 string (default: 24).
     * @return string The generated CUID-2 string.
     * @throws InvalidArgumentException|Exception
     */
    public static function cuid2(int $maxLength = 24): string
    {
        ($maxLength < 4 || $maxLength > 32) && throw new InvalidArgumentException(
            'maxLength must be between 4 and 32',
        );

        self::$cuid2counter ??= random_int(0, PHP_INT_MAX);
        $hash = hash_init('sha3-512');
        hash_update($hash, (new DateTimeImmutable('now'))->format('Uv'));
        hash_update($hash, (string) self::$cuid2counter++);
        hash_update($hash, bin2hex(random_bytes($maxLength)));
        hash_update($hash, self::cuid2Fingerprint());
        $encoded = self::hexToBase36(hash_final($hash));

        return substr(str_pad($encoded, $maxLength, '0'), 0, $maxLength);
    }

    /**
     * Checks whether a CUID2 string is valid.
     */
    public static function isCuid2(string $id): bool
    {
        return preg_match('/^[0-9a-z]{4,32}$/', $id) === 1;
    }

    /**
     * Checks whether a NanoID string is valid.
     *
     * @param int|null $size Optional exact size to validate against.
     */
    public static function isNanoId(string $id, ?int $size = null): bool
    {
        if ($size !== null && strlen($id) !== $size) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1;
    }

    /**
     * Generates Nano ID of specified size.
     *
     * @param int $size The size of the nano ID (default: 21).
     * @return string The generated nano ID.
     * @throws Exception
     */
    public static function nanoId(int $size = 21): string
    {
        ($size < 1) && throw new InvalidArgumentException('size must be greater than 0');

        return substr(
            str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes($size))),
            0,
            $size,
        );
    }

    /**
     * Parses CUID2 information.
     *
     * @return array{isValid: bool, length: int}
     */
    public static function parseCuid2(string $id): array
    {
        return [
            'isValid' => self::isCuid2($id),
            'length' => strlen($id),
        ];
    }

    /**
     * Parses NanoID information.
     *
     * @param int|null $size Optional expected size.
     * @return array{isValid: bool, length: int, alphabet: string}
     */
    public static function parseNanoId(string $id, ?int $size = null): array
    {
        return [
            'isValid' => self::isNanoId($id, $size),
            'length' => strlen($id),
            'alphabet' => 'base64url',
        ];
    }

    /**
     * Generates a fingerprint for the cuid2 algorithm using the SHA3-512 hash function.
     *
     * @return string The hexadecimal representation of the fingerprint.
     * @throws Exception
     */
    private static function cuid2Fingerprint(): string
    {
        $hash = hash_init('sha3-512');
        hash_update($hash, gethostname() ?: substr(str_shuffle('abcdefghjkmnpqrstvwxyz0123456789'), 0, 32));
        hash_update($hash, (string) random_int(1, 32768));
        hash_update($hash, bin2hex(random_bytes(32)));

        return hash_final($hash);
    }

    /**
     * Converts a base16 string to base36 using BCMath for large integer safety.
     *
     * @param string $hex A hexadecimal string.
     */
    private static function hexToBase36(string $hex): string
    {
        $hex = strtolower(ltrim($hex, '0'));
        if ($hex === '') {
            return '0';
        }

        $decimal = '0';
        $hexChars = '0123456789abcdef';
        foreach (str_split($hex) as $char) {
            ($value = strpos($hexChars, $char)) !== false || throw new InvalidArgumentException(
                'Invalid hexadecimal string provided',
            );
            $decimal = bcadd(bcmul($decimal, '16'), (string) $value);
        }

        $encoded = '';
        while (bccomp($decimal, '0') === 1) {
            $remainder = (int) bcmod($decimal, '36');
            $encoded = self::$cuid2Alphabet[$remainder] . $encoded;
            $decimal = bcdiv($decimal, '36', 0);
        }

        return $encoded;
    }
}
