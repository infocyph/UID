<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

final class NumericIdCodec
{
    /**
     * @param callable(string):bool $validator
     * @throws \InvalidArgumentException
     */
    public static function bytesFromDecimal(
        string $id,
        int $byteLength,
        callable $validator,
        string $invalidIdMessage,
    ): string {
        $validator($id) || throw new \InvalidArgumentException($invalidIdMessage);

        return DecimalBytes::toFixedBytes($id, $byteLength);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function decimalFromBase(string $encoded, int $base, int $byteLength): string
    {
        return self::decimalFromBytes(BaseEncoder::decodeToBytes($encoded, $base, $byteLength), $byteLength);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function decimalFromBytes(string $bytes, int $byteLength): string
    {
        self::assertByteLength($bytes, $byteLength);

        return DecimalBytes::fromBytes($bytes);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private static function assertByteLength(string $bytes, int $byteLength): void
    {
        if (strlen($bytes) !== $byteLength) {
            throw new \InvalidArgumentException(
                sprintf('Expected exactly %d bytes, got %d bytes', $byteLength, strlen($bytes)),
            );
        }
    }
}
