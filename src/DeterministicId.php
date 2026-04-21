<?php

declare(strict_types=1);

namespace Infocyph\UID;

use Infocyph\UID\Support\BaseEncoder;

final class DeterministicId
{
    /**
     * Generates a deterministic opaque ID from payload.
     */
    public static function fromPayload(string $payload, int $length = 24, string $namespace = 'default'): string
    {
        $hash = hash('sha3-256', $namespace . '|' . $payload, true);
        $encoded = BaseEncoder::encodeBytes($hash, 62);

        return substr(str_pad($encoded, $length, '0'), 0, $length);
    }
}
