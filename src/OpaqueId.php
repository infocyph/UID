<?php

declare(strict_types=1);

namespace Infocyph\UID;

use Exception;
use Infocyph\UID\Support\BaseEncoder;
use InvalidArgumentException;

final class OpaqueId
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private const MAX_RANDOM_LENGTH = 1024;

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
        if ($length < 1 || $length > self::MAX_RANDOM_LENGTH) {
            throw new InvalidArgumentException('length must be between 1 and 1024');
        }

        $id = '';
        $idLength = 0;
        while ($idLength < $length) {
            $remaining = $length - $idLength;
            $chunkLength = intdiv(($remaining * 256) + 247, 248);
            if ($chunkLength < 1) {
                throw new \LogicException('Unable to calculate opaque ID entropy length');
            }

            $bytes = random_bytes($chunkLength);
            $byteLength = strlen($bytes);
            for ($index = 0; $index < $byteLength; ++$index) {
                $value = ord($bytes[$index]);
                if ($value >= 248) {
                    continue;
                }

                $id .= self::ALPHABET[$value % 62];
                ++$idLength;
                if ($idLength === $length) {
                    break;
                }
            }
        }

        return $id;
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
