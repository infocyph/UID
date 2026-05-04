<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Exceptions\UIDException;

final class OutputFormatter
{
    /**
     * Formats an unsigned numeric string according to desired output type.
     *
     * @throws UIDException
     */
    public static function formatNumeric(int|string $value, IdOutputType $type): int|string
    {
        $decimal = (string) $value;

        return match ($type) {
            IdOutputType::STRING => $decimal,
            IdOutputType::INT => self::toInt($decimal),
            IdOutputType::BINARY => self::toBinary64($decimal),
        };
    }

    /**
     * @throws UIDException
     */
    private static function toBinary64(string $decimal): string
    {
        try {
            return DecimalBytes::toFixedBytes($decimal, 8);
        } catch (\InvalidArgumentException $exception) {
            throw new UIDException('Unable to convert numeric ID to binary', 0, $exception);
        }
    }

    /**
     * @throws UIDException
     */
    private static function toInt(string $decimal): int
    {
        if (UnsignedDecimal::compare($decimal, (string) PHP_INT_MAX) === 1) {
            throw new UIDException('Numeric ID exceeds PHP_INT_MAX; use string or binary output');
        }

        return (int) $decimal;
    }
}
