<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Infocyph\UID\Enums\UlidGenerationMode;
use Infocyph\UID\Exceptions\ULIDException;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\DecimalBytes;

final class ULID
{
    private static string $encodingChars = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private static int $encodingLength = 32;

    private static int $lastGenTime = 0;

    /** @var array<int, int> */
    private static array $lastRandChars = [];

    private static int $randomLength = 16;

    private static int $timeLength = 10;

    /**
     * Decodes one of bases: 16, 32, 36, 58, 62 into canonical ULID.
     *
     * @throws ULIDException
     */
    public static function fromBase(string $encoded, int $base): string
    {
        try {
            return self::fromBytes(BaseEncoder::decodeToBytes($encoded, $base, 16));
        } catch (\InvalidArgumentException $exception) {
            throw new ULIDException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Converts 16-byte ULID binary data to canonical ULID string.
     *
     * @throws ULIDException
     */
    public static function fromBytes(string $bytes): string
    {
        if (strlen($bytes) !== 16) {
            throw new ULIDException('ULID binary data must be exactly 16 bytes');
        }

        $decimal = DecimalBytes::fromBytes($bytes);

        $encoded = str_repeat('0', 26);
        $chars = str_split($encoded);
        for ($index = 25; $index >= 0; --$index) {
            $remainder = (int) bcmod($decimal, '32');
            $chars[$index] = self::$encodingChars[$remainder];
            $decimal = bcdiv($decimal, '32', 0);
        }

        $ulid = implode('', $chars);
        self::isValid($ulid) || throw new ULIDException('Converted bytes produced invalid ULID');

        return $ulid;
    }

    /**
     * Generates a ULID (Universally Unique Lexicographically Sortable Identifier).
     *
     * @throws Exception
     */
    public static function generate(
        ?DateTimeInterface $dateTime = null,
        UlidGenerationMode $mode = UlidGenerationMode::MONOTONIC,
    ): string {
        $time = (int) ($dateTime ?? new DateTimeImmutable('now'))->format('Uv');

        $isMonotonic = $mode === UlidGenerationMode::MONOTONIC;
        $isDuplicate = $isMonotonic && $time === self::$lastGenTime;
        self::$lastGenTime = $time;

        $timeChars = self::encodeTime($time);
        if (!$isMonotonic || !$isDuplicate || count(self::$lastRandChars) !== self::$randomLength) {
            self::resetRandomState();
        } elseif (!self::incrementRandomState()) {
            if ($dateTime !== null) {
                throw new ULIDException('Monotonic ULID overflow for the provided timestamp');
            }

            $time = self::waitForNextMillisecond(self::$lastGenTime);
            self::$lastGenTime = $time;
            $timeChars = self::encodeTime($time);
            self::resetRandomState();
        }

        return $timeChars . self::randomCharsFromState();
    }

    /**
     * Generates ULID in monotonic mode.
     *
     * @throws Exception
     */
    public static function generateMonotonic(?DateTimeInterface $dateTime = null): string
    {
        return self::generate($dateTime, UlidGenerationMode::MONOTONIC);
    }

    /**
     * Generates ULID in strict-random mode.
     *
     * @throws Exception
     */
    public static function generateRandom(?DateTimeInterface $dateTime = null): string
    {
        return self::generate($dateTime, UlidGenerationMode::RANDOM);
    }

    /**
     * Get the DateTimeImmutable object from the ULID.
     *
     * @param string $ulid The ULID to extract the timestamp from.
     * @return DateTimeImmutable The extracted DateTimeImmutable object.
     * @throws ULIDException|Exception
     */
    public static function getTime(string $ulid): DateTimeImmutable
    {
        if (!self::isValid($ulid)) {
            throw new ULIDException('Invalid ULID string');
        }

        $timeChars = str_split(strrev(substr($ulid, 0, self::$timeLength)));

        $time = 0;
        foreach ($timeChars as $index => $char) {
            $encodingIndex = strripos(self::$encodingChars, $char);
            $time += ($encodingIndex * self::$encodingLength ** $index);
        }

        $time = str_split((string) $time, max(1, self::$timeLength));
        $time[1] ??= '0';

        if ($time[0] > (time() + (86400 * 365 * 10))) {
            throw new ULIDException('Invalid ULID string: timestamp too large');
        }

        return new DateTimeImmutable("@$time[0].$time[1]");
    }

    /**
     * Check if ULID is valid
     *
     * @param string $ulid The ULID to be checked
     */
    public static function isValid(string $ulid): bool
    {
        return (bool) preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $ulid);
    }

    /**
     * Encodes ULID bytes into one of bases: 16, 32, 36, 58, 62.
     *
     * @throws ULIDException
     */
    public static function toBase(string $ulid, int $base): string
    {
        return BaseEncoder::encodeBytes(self::toBytes($ulid), $base);
    }

    /**
     * Converts a ULID string to 16-byte binary representation.
     *
     * @throws ULIDException
     */
    public static function toBytes(string $ulid): string
    {
        if (!self::isValid($ulid)) {
            throw new ULIDException('Invalid ULID string');
        }

        try {
            return DecimalBytes::toFixedBytes(self::decodeToDecimal($ulid), 16);
        } catch (\InvalidArgumentException $exception) {
            throw new ULIDException('Unable to convert ULID to bytes', 0, $exception);
        }
    }

    /**
     * Decodes ULID base32 text to an arbitrary precision decimal string.
     *
     * @throws ULIDException
     */
    private static function decodeToDecimal(string $ulid): string
    {
        $decimal = '0';
        foreach (str_split($ulid) as $char) {
            $index = strpos(self::$encodingChars, $char);
            if ($index === false) {
                throw new ULIDException('Invalid ULID character');
            }

            $decimal = bcadd(bcmul($decimal, '32'), (string) $index);
        }

        return $decimal;
    }

    /**
     * Encodes the ULID millisecond timestamp to Crockford base32.
     *
     * @param int $time Timestamp in milliseconds.
     */
    private static function encodeTime(int $time): string
    {
        $timeChars = '';
        for ($i = self::$timeLength - 1; $i >= 0; $i--) {
            $mod = $time % self::$encodingLength;
            $timeChars = self::$encodingChars[$mod] . $timeChars;
            $time = ($time - $mod) / self::$encodingLength;
        }

        return $timeChars;
    }

    private static function incrementRandomState(): bool
    {
        for ($index = self::$randomLength - 1; $index >= 0; --$index) {
            if (self::$lastRandChars[$index] < 31) {
                self::$lastRandChars[$index]++;

                return true;
            }

            self::$lastRandChars[$index] = 0;
        }

        return false;
    }

    private static function randomCharsFromState(): string
    {
        $randChars = '';
        for ($index = 0; $index < self::$randomLength; $index++) {
            $randChars .= self::$encodingChars[self::$lastRandChars[$index]];
        }

        return $randChars;
    }

    /**
     * @throws Exception
     */
    private static function resetRandomState(): void
    {
        for ($index = 0; $index < self::$randomLength; $index++) {
            self::$lastRandChars[$index] = random_int(0, 31);
        }
    }

    private static function waitForNextMillisecond(int $lastTimestamp): int
    {
        do {
            usleep(1000);
            $next = (int) (new DateTimeImmutable('now'))->format('Uv');
        } while ($next <= $lastTimestamp);

        return $next;
    }
}
