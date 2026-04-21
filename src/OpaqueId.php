<?php

declare(strict_types=1);

namespace Infocyph\UID;

use Exception;
use Infocyph\UID\Support\BaseEncoder;

final class OpaqueId
{
    /**
     * Encodes an integer in a hashid-style opaque token.
     */
    public static function fromInt(int $value, string $salt = ''): string
    {
        $saltMask = crc32($salt);
        $mixed = $value ^ $saltMask;
        $bytes = pack('J', $mixed);

        return BaseEncoder::encodeBytes($bytes, 62);
    }

    /**
     * Generates a short opaque random ID.
     *
     * @throws Exception
     */
    public static function random(int $length = 12): string
    {
        $bytes = random_bytes(max(8, $length));

        return substr(BaseEncoder::encodeBytes($bytes, 62), 0, $length);
    }

    /**
     * Decodes an opaque token back to integer.
     *
     * @throws Exception
     */
    public static function toInt(string $token, string $salt = ''): int
    {
        $bytes = BaseEncoder::decodeToBytes($token, 62, 8);
        $unpacked = unpack('J', $bytes);
        ($unpacked !== false) || throw new Exception('Unable to decode opaque token');
        $value = $unpacked[1] ?? null;
        is_int($value) || throw new Exception('Unable to decode opaque token');
        $saltMask = crc32($salt);

        return $value ^ $saltMask;
    }
}
