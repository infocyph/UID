<?php

declare(strict_types=1);

namespace Infocyph\UID;

use Infocyph\UID\Support\RandomSampler;

final class RandomId
{
    public const DEFAULT_ALPHABET = 'abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function generate(int $length = 21, string $alphabet = self::DEFAULT_ALPHABET): string
    {
        return RandomSampler::generate($length, $alphabet);
    }

    public static function isValid(
        string $id,
        ?int $length = null,
        string $alphabet = self::DEFAULT_ALPHABET,
    ): bool {
        if ($id === '' || ($length !== null && strlen($id) !== $length)) {
            return false;
        }

        return RandomSampler::containsOnly($id, $alphabet);
    }
}
