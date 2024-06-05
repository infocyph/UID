<?php

namespace Infocyph\UID;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Infocyph\UID\Exceptions\ULIDException;

final class ULID
{
    private static string $encodingChars = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private static int $encodingLength = 32;
    private static int $timeLength = 10;
    private static int $randomLength = 16;
    private static int $lastGenTime = 0;
    private static array $lastRandChars = [];


    /**
     * Generates a ULID (Universally Unique Lexicographically Sortable Identifier).
     *
     * @param DateTimeInterface|null $dateTime
     * @return string
     * @throws Exception
     */
    public static function generate(?DateTimeInterface $dateTime = null): string
    {
        $time = (int)($dateTime ?? new DateTimeImmutable('now'))->format('Uv');

        $isDuplicate = $time === self::$lastGenTime;
        self::$lastGenTime = $time;

        // Generate time characters
        $timeChars = '';
        for ($i = self::$timeLength - 1; $i >= 0; $i--) {
            $mod = $time % self::$encodingLength;
            $timeChars = self::$encodingChars[$mod] . $timeChars;
            $time = ($time - $mod) / self::$encodingLength;
        }

        // Generate random characters
        $randChars = '';
        if (!$isDuplicate) {
            for ($i = 0; $i < self::$randomLength; $i++) {
                self::$lastRandChars[$i] = random_int(0, 31);
            }
        } else {
            for ($i = self::$randomLength - 1; $i >= 0 && self::$lastRandChars[$i] === 31; $i--) {
                self::$lastRandChars[$i] = 0;
            }
            self::$lastRandChars[$i]++;
        }
        for ($i = 0; $i < self::$randomLength; $i++) {
            $randChars .= self::$encodingChars[self::$lastRandChars[$i]];
        }

        return $timeChars . $randChars;
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

        $time = str_split($time, self::$timeLength);

        if ($time[0] > (time() + (86400 * 365 * 10))) {
            throw new ULIDException('Invalid ULID string: timestamp too large');
        }

        return new DateTimeImmutable("@$time[0].$time[1]");
    }

    /**
     * Check if ULID is valid
     *
     * @param string $ulid The ULID to be checked
     * @return bool
     */
    public static function isValid(string $ulid): bool
    {
        return (bool)preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $ulid);
    }
}
