<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Contracts\IdAlgorithmInterface;
use InvalidArgumentException;

final class CUID2 implements IdAlgorithmInterface
{
    private static string $alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';

    private static int $counter;

    /**
     * Generates a CUID2 string with a specified maximum length.
     *
     * @throws InvalidArgumentException|Exception
     */
    public static function generate(int $length = 24): string
    {
        ($length < 4 || $length > 32) && throw new InvalidArgumentException(
            'length must be between 4 and 32',
        );

        self::$counter ??= random_int(0, PHP_INT_MAX);
        $hash = hash_init('sha3-512');
        hash_update($hash, (new DateTimeImmutable('now'))->format('Uv'));
        hash_update($hash, (string) self::$counter++);
        hash_update($hash, bin2hex(random_bytes($length)));
        hash_update($hash, self::fingerprint());
        $encoded = self::hexToBase36(hash_final($hash));

        return substr(str_pad($encoded, $length, '0'), 0, $length);
    }

    /**
     * Checks whether a CUID2 string is valid.
     */
    public static function isValid(string $id, ?int $length = null): bool
    {
        if ($length !== null && strlen($id) !== $length) {
            return false;
        }

        return preg_match('/^[0-9a-z]{4,32}$/', $id) === 1;
    }

    /**
     * Parses CUID2 information.
     *
     * @return array{isValid: bool, length: int}
     */
    public static function parse(string $id, ?int $length = null): array
    {
        return [
            'isValid' => self::isValid($id, $length),
            'length' => strlen($id),
        ];
    }

    /**
     * Generates a fingerprint for the CUID2 algorithm using SHA3-512.
     *
     * @throws Exception
     */
    private static function fingerprint(): string
    {
        $hash = hash_init('sha3-512');
        hash_update($hash, gethostname() ?: substr(str_shuffle('abcdefghjkmnpqrstvwxyz0123456789'), 0, 32));
        hash_update($hash, (string) random_int(1, 32768));
        hash_update($hash, bin2hex(random_bytes(32)));

        return hash_final($hash);
    }

    /**
     * Converts a base16 string to base36 using BCMath for large integer safety.
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
            $encoded = self::$alphabet[$remainder] . $encoded;
            $decimal = bcdiv($decimal, '36', 0);
        }

        return $encoded;
    }
}
