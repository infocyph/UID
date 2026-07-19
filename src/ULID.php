<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Infocyph\UID\Enums\UlidGenerationMode;
use Infocyph\UID\Exceptions\ULIDException;
use Infocyph\UID\Support\BaseEncoder;

final class ULID
{
    private const MAX_TIMESTAMP = 281_474_976_710_655;

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

        $ulid = '';
        $buffer = 0;
        $bits = 2;
        for ($index = 0; $index < 16; ++$index) {
            $buffer = ($buffer << 8) | ord($bytes[$index]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $ulid .= self::$encodingChars[($buffer >> $bits) & 31];
                $buffer &= $bits === 0 ? 0 : (1 << $bits) - 1;
            }
        }

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
        $time = $dateTime === null
            ? (int) floor(microtime(true) * 1000)
            : (int) $dateTime->format('Uv');
        self::assertTimestamp($time);

        $isMonotonic = $mode === UlidGenerationMode::MONOTONIC;
        if ($isMonotonic && $dateTime === null && $time < self::$lastGenTime) {
            $time = self::$lastGenTime;
        }

        $isDuplicate = $isMonotonic && $time === self::$lastGenTime;
        if ($isMonotonic) {
            self::$lastGenTime = $time;
        }

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

        $time = 0;
        for ($index = 0; $index < self::$timeLength; ++$index) {
            $encodingIndex = strpos(self::$encodingChars, $ulid[$index]);
            $encodingIndex !== false || throw new ULIDException('Invalid ULID character');
            $time = ($time * self::$encodingLength) + $encodingIndex;
        }

        return new DateTimeImmutable(
            '@'
            . intdiv($time, 1000)
            . '.'
            . str_pad((string) (($time % 1000) * 1000), 6, '0', STR_PAD_LEFT),
        );
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

        $bytes = '';
        $buffer = 0;
        $bits = -2;
        for ($index = 0; $index < 26; ++$index) {
            $alphabetIndex = strpos(self::$encodingChars, $ulid[$index]);
            $alphabetIndex !== false || throw new ULIDException('Invalid ULID character');
            $buffer = ($buffer << 5) | $alphabetIndex;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $bytes .= chr(($buffer >> $bits) & 0xff);
                $buffer &= $bits === 0 ? 0 : (1 << $bits) - 1;
            }
        }

        return $bytes;
    }

    private static function assertTimestamp(int $timestamp): void
    {
        if ($timestamp < 0 || $timestamp > self::MAX_TIMESTAMP) {
            throw new ULIDException('ULID timestamp must fit in an unsigned 48-bit integer');
        }
    }

    /**
     * Encodes the ULID millisecond timestamp to Crockford base32.
     *
     * @param int $time Timestamp in milliseconds.
     */
    private static function encodeTime(int $time): string
    {
        $timeChars = '';
        for ($i = self::$timeLength - 1; $i >= 0; --$i) {
            $mod = $time % self::$encodingLength;
            $timeChars = self::$encodingChars[$mod] . $timeChars;
            $time = intdiv($time, self::$encodingLength);
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
        $random = random_bytes(10);
        $buffer = 0;
        $bits = 0;
        $stateIndex = 0;
        for ($index = 0; $index < 10; ++$index) {
            $buffer = ($buffer << 8) | ord($random[$index]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                self::$lastRandChars[$stateIndex++] = ($buffer >> $bits) & 31;
                $buffer &= $bits === 0 ? 0 : (1 << $bits) - 1;
            }
        }
    }

    private static function waitForNextMillisecond(int $lastTimestamp): int
    {
        do {
            usleep(1000);
            $next = (int) floor(microtime(true) * 1000);
        } while ($next <= $lastTimestamp);

        return $next;
    }
}
