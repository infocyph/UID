<?php

declare(strict_types=1);

namespace Infocyph\UID;

use Exception;
use Infocyph\UID\Support\BaseEncoder;
use InvalidArgumentException;

final class CUID2
{
    private const INITIAL_COUNTER_MAX = 476_782_367;

    private const LETTERS = 'abcdefghijklmnopqrstuvwxyz';

    private static int $counter;

    private static ?string $fingerprint = null;

    private static ?int $sourcePid = null;

    /**
     * Generates a CUID2 string with a specified maximum length.
     *
     * @throws InvalidArgumentException|Exception
     */
    public static function generate(int $length = 24): string
    {
        ($length < 2 || $length > 32) && throw new InvalidArgumentException(
            'length must be between 2 and 32',
        );

        self::ensureProcessState();
        $time = (string) (int) floor(microtime(true) * 1000);
        $counter = (string) self::$counter;
        if (self::$counter === PHP_INT_MAX) {
            self::$counter = random_int(0, self::INITIAL_COUNTER_MAX);
        } else {
            ++self::$counter;
        }
        $salt = random_bytes($length);
        $fingerprint = self::fingerprint();
        $hashInput = pack('N', strlen($time)) . $time
            . pack('N', strlen($counter)) . $counter
            . pack('N', strlen($salt)) . $salt
            . pack('N', strlen($fingerprint)) . $fingerprint;
        $encoded = BaseEncoder::encodeBytes(hash('sha3-512', $hashInput, true), 36);
        $firstLetter = self::LETTERS[random_int(0, 25)];

        return $firstLetter . substr($encoded, 1, $length - 1);
    }

    /**
     * Checks whether a CUID2 string is valid.
     */
    public static function isValid(string $id, ?int $length = null): bool
    {
        if ($length !== null && strlen($id) !== $length) {
            return false;
        }

        return preg_match('/^[a-z][0-9a-z]{1,31}$/D', $id) === 1;
    }

    /**
     * Parses CUID2 information.
     *
     * @return array{length: int}
     */
    public static function parse(string $id, ?int $length = null): array
    {
        self::isValid($id, $length) || throw new InvalidArgumentException('Invalid CUID2 string');

        return ['length' => strlen($id)];
    }

    private static function ensureProcessState(): void
    {
        $pid = (int) getmypid();
        if (self::$sourcePid === $pid) {
            return;
        }

        self::$sourcePid = $pid;
        self::$counter = random_int(0, self::INITIAL_COUNTER_MAX);
        self::$fingerprint = null;
    }

    /**
     * Generates a fingerprint for the CUID2 algorithm using SHA3-512.
     *
     * @throws Exception
     */
    private static function fingerprint(): string
    {
        if (self::$fingerprint !== null) {
            return self::$fingerprint;
        }

        $host = gethostname();
        $source = ($host === false ? '' : $host)
            . "\0"
            . (int) getmypid()
            . random_bytes(32);

        return self::$fingerprint = substr(
            BaseEncoder::encodeBytes(hash('sha3-512', $source, true), 36),
            0,
            32,
        );
    }
}
