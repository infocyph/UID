<?php

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

final class RandomId
{
    private static int $cuid2counter;

    /**
     * Generates Nano ID of specified size.
     *
     * @param int $size The size of the nano ID (default: 21).
     * @return string The generated nano ID.
     * @throws Exception
     */
    public static function nanoId(int $size = 21): string
    {
        return substr(
            str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes($size))),
            0,
            $size
        );
    }

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
            'maxLength must be between 4 and 32'
        );

        self::$cuid2counter ??= (int)(random_int(PHP_INT_MIN, PHP_INT_MAX) * 476782367);
        $hash = hash_init('sha3-512');
        hash_update($hash, (new DateTimeImmutable('now'))->format('Uv'));
        hash_update($hash, (string)self::$cuid2counter++);
        hash_update($hash, bin2hex(random_bytes($maxLength)));
        hash_update($hash, self::cuid2Fingerprint());
        $hash = hash_final($hash);
        return substr(base_convert($hash, 16, 36), 0, $maxLength - 1);
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
        hash_update($hash, (string)random_int(1, 32768));
        hash_update($hash, bin2hex(random_bytes(32)));
        return bin2hex(hash_final($hash));
    }
}
