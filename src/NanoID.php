<?php

declare(strict_types=1);

namespace Infocyph\UID;

use Exception;
use Infocyph\UID\Contracts\IdAlgorithmInterface;
use InvalidArgumentException;

final class NanoID implements IdAlgorithmInterface
{
    private const MAX_LENGTH = 1_048_576;

    /**
     * Generates a NanoID string with the requested size.
     *
     * @throws Exception
     */
    public static function generate(int $length = 21): string
    {
        if ($length < 1 || $length > self::MAX_LENGTH) {
            throw new InvalidArgumentException('length must be between 1 and 1048576');
        }

        $byteLength = intdiv(($length * 3) + 3, 4);
        if ($byteLength < 1) {
            throw new \LogicException('Unable to calculate NanoID entropy length');
        }

        return substr(
            rtrim(strtr(base64_encode(random_bytes($byteLength)), '+/', '-_'), '='),
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
