<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

final class BinaryUnpack
{
    /**
     * @throws \Exception
     */
    public static function u16(string $bytes, string $error): int
    {
        return self::value(unpack('n', $bytes), $error);
    }

    /**
     * @throws \Exception
     */
    public static function u24(string $bytes, string $error): int
    {
        return self::value(unpack('N', chr(0) . $bytes), $error);
    }

    /**
     * @throws \Exception
     */
    public static function u32(string $bytes, string $error): int
    {
        return self::value(unpack('N', $bytes), $error);
    }

    /**
     * @param array<int|string, mixed>|false $unpacked
     * @throws \Exception
     */
    private static function value(array|false $unpacked, string $error): int
    {
        ($unpacked !== false) || throw new \Exception($error);
        $value = $unpacked[1] ?? null;
        is_int($value) || throw new \Exception($error);

        return $value;
    }
}
