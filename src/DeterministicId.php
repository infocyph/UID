<?php

declare(strict_types=1);

namespace Infocyph\UID;

use InvalidArgumentException;

final class DeterministicId
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private const DOMAIN = "infocyph.uid.deterministic.v5\0";

    private const MAX_LENGTH = 43;

    /**
     * Generates a deterministic opaque ID from payload.
     */
    public static function fromPayload(string $payload, int $length = 24, string $namespace = 'default'): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('length must be greater than zero');
        }

        if ($length > self::MAX_LENGTH) {
            throw new InvalidArgumentException('length must not exceed 43 characters');
        }

        $input = self::DOMAIN
            . pack('N', strlen($namespace))
            . $namespace
            . pack('N', strlen($payload))
            . $payload;
        $encoded = '';
        $counter = 0;

        while (strlen($encoded) < $length) {
            $bytes = hash('sha3-512', $input . pack('N', $counter++), true);
            for ($index = 0; $index < 64; ++$index) {
                $value = ord($bytes[$index]);
                if ($value >= 248) {
                    continue;
                }

                $encoded .= self::ALPHABET[$value % 62];
                if (strlen($encoded) === $length) {
                    break;
                }
            }
        }

        return $encoded;
    }
}
