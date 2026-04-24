<?php

declare(strict_types=1);

namespace Infocyph\UID;

use Exception;
use Infocyph\UID\Contracts\IdAlgorithmInterface;
use InvalidArgumentException;

final class NanoID implements IdAlgorithmInterface
{
    /**
     * Generates a NanoID string with the requested size.
     *
     * @throws Exception
     */
    public static function generate(int $length = 21): string
    {
        ($length < 1) && throw new InvalidArgumentException('length must be greater than 0');

        return substr(
            str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes($length))),
            0,
            $length,
        );
    }

    /**
     * Checks whether a NanoID string is valid.
     */
    public static function isValid(string $id, ?int $length = null): bool
    {
        if ($length !== null && strlen($id) !== $length) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1;
    }

    /**
     * Parses NanoID information.
     *
     * @return array{isValid: bool, length: int, alphabet: string}
     */
    public static function parse(string $id, ?int $length = null): array
    {
        return [
            'isValid' => self::isValid($id, $length),
            'length' => strlen($id),
            'alphabet' => 'base64url',
        ];
    }
}
