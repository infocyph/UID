<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

final class DecimalBytes
{
    private const MAX_BYTE_LENGTH = 1024;

    public static function fromBytes(string $bytes): string
    {
        return BaseEncoder::encodeBytes($bytes, 10);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function toFixedBytes(string $decimal, int $byteLength): string
    {
        if ($byteLength < 1 || $byteLength > self::MAX_BYTE_LENGTH) {
            throw new \InvalidArgumentException('Byte length must be between 1 and 1024');
        }

        if ($decimal === '' || !ctype_digit($decimal)) {
            throw new \InvalidArgumentException('Decimal value must contain only digits');
        }

        return BaseEncoder::decodeToBytes(UnsignedDecimal::normalize($decimal), 10, $byteLength);
    }
}
