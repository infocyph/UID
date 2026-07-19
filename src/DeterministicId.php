<?php

declare(strict_types=1);

namespace Infocyph\UID;

use Infocyph\UID\Support\BaseEncoder;
use InvalidArgumentException;

final class DeterministicId
{
    private const MAX_LENGTH = 43;

    /**
     * Generates a deterministic opaque ID from payload.
     */
    public static function fromPayload(string $payload, int $length = 24, string $namespace = 'default'): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('length must be greater than zero');
        }

        if (str_contains($namespace, '|')) {
            throw new InvalidArgumentException('namespace must not contain the reserved delimiter');
        }

        if ($length > self::MAX_LENGTH) {
            throw new InvalidArgumentException('length must not exceed 43 characters');
        }

        $hash = hash('sha3-256', $namespace . '|' . $payload, true);
        $encoded = str_pad(BaseEncoder::encodeBytes($hash, 62), self::MAX_LENGTH, '0');

        return substr($encoded, 0, $length);
    }
}
