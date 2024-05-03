<?php

namespace Infocyph\UID;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Infocyph\UID\Exceptions\ULIDException;

class ULID
{
    private static string $encodingChars = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private static int $encodingLength = 32;
    private static int $timeLength = 10;
    private static int $randomLength = 16;
    private static int $lastGenTime = 0;
    private static array $lastRandChars = [];


    /**
     * Generates a ULID (Universally Unique Lexicographically Identifier).
     *
     * @param DateTimeInterface|null $dateTime
     * @return string
     * @throws Exception
     */
    public static function generate(?DateTimeInterface $dateTime = null): string
    {
        $time = (int)($dateTime ?? new DateTimeImmutable('now'))->format('Uv');

        $isDuplicate = $time === static::$lastGenTime;
        static::$lastGenTime = $time;

        // Generate time characters
        $timeChars = '';
        for ($i = static::$timeLength - 1; $i >= 0; $i--) {
            $mod = $time % static::$encodingLength;
            $timeChars = static::$encodingChars[$mod] . $timeChars;
            $time = ($time - $mod) / static::$encodingLength;
        }

        // Generate random characters
        $randChars = '';
        if (!$isDuplicate) {
            for ($i = 0; $i < static::$randomLength; $i++) {
                static::$lastRandChars[$i] = random_int(0, 31);
            }
        } else {
            for ($i = static::$randomLength - 1; $i >= 0 && static::$lastRandChars[$i] === 31; $i--) {
                static::$lastRandChars[$i] = 0;
            }
            static::$lastRandChars[$i]++;
        }
        for ($i = 0; $i < static::$randomLength; $i++) {
            $randChars .= static::$encodingChars[static::$lastRandChars[$i]];
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
        if (!static::isValid($ulid)) {
            throw new ULIDException('Invalid ULID string');
        }

        $timeChars = str_split(strrev(substr($ulid, 0, static::$timeLength)));

        $time = 0;
        foreach ($timeChars as $index => $char) {
            $encodingIndex = strripos(static::$encodingChars, $char);
            $time += ($encodingIndex * pow(static::$encodingLength, $index));
        }

        $time = str_split($time, static::$timeLength);

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
