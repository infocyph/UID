<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use InvalidArgumentException;

final class RandomSampler
{
    public static function assertAlphabet(string $alphabet): void
    {
        $length = strlen($alphabet);
        if ($length < 2 || $length > 256) {
            throw new InvalidArgumentException('alphabet must contain between 2 and 256 symbols');
        }

        $seen = [];
        for ($index = 0; $index < $length; ++$index) {
            $symbol = $alphabet[$index];
            if (isset($seen[$symbol])) {
                throw new InvalidArgumentException('alphabet must not contain duplicate symbols');
            }

            $seen[$symbol] = true;
        }
    }

    public static function containsOnly(string $value, string $alphabet): bool
    {
        self::assertAlphabet($alphabet);
        $allowed = [];
        $alphabetLength = strlen($alphabet);
        for ($index = 0; $index < $alphabetLength; ++$index) {
            $allowed[$alphabet[$index]] = true;
        }

        $length = strlen($value);
        for ($index = 0; $index < $length; ++$index) {
            if (!isset($allowed[$value[$index]])) {
                return false;
            }
        }

        return true;
    }

    public static function generate(int $length, string $alphabet): string
    {
        self::assertAlphabet($alphabet);

        if ($length < 1 || $length > 1024) {
            throw new InvalidArgumentException('length must be between 1 and 1024');
        }

        $alphabetLength = strlen($alphabet);
        $limit = intdiv(256, $alphabetLength) * $alphabetLength;
        $result = '';
        $resultLength = 0;

        while ($resultLength < $length) {
            $remaining = $length - $resultLength;
            $chunkLength = max(1, intdiv(($remaining * 256) + $limit - 1, $limit));
            $bytes = random_bytes($chunkLength);
            $sample = self::mapAcceptedBytes($bytes, $alphabet, $alphabetLength, $limit);
            $accepted = min(strlen($sample), $remaining);
            $result .= substr($sample, 0, $accepted);
            $resultLength += $accepted;
        }

        return $result;
    }

    public static function mapBytes(string $bytes, string $alphabet): string
    {
        self::assertAlphabet($alphabet);
        $alphabetLength = strlen($alphabet);

        return self::mapAcceptedBytes(
            $bytes,
            $alphabet,
            $alphabetLength,
            intdiv(256, $alphabetLength) * $alphabetLength,
        );
    }

    private static function mapAcceptedBytes(
        string $bytes,
        string $alphabet,
        int $alphabetLength,
        int $limit,
    ): string {
        $result = '';
        $byteLength = strlen($bytes);
        for ($index = 0; $index < $byteLength; ++$index) {
            $value = ord($bytes[$index]);
            if ($value < $limit) {
                $result .= $alphabet[$value % $alphabetLength];
            }
        }

        return $result;
    }
}
