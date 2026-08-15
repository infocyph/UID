<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Infocyph\UID\Exceptions\TypeIDException;
use Infocyph\UID\Support\TypeIdCodec;

final class TypeID
{
    public static function fromUuid(string $type, string $uuid): string
    {
        self::assertPrefix($type);

        try {
            $suffix = TypeIdCodec::encode(UUID::toBytes($uuid));
        } catch (\Throwable $exception) {
            throw new TypeIDException('Invalid UUID for TypeID', 0, $exception);
        }

        return $type === '' ? $suffix : $type . '_' . $suffix;
    }

    public static function generate(string $type = ''): string
    {
        return self::fromUuid($type, UUID::v7());
    }

    public static function isValid(string $typeId): bool
    {
        try {
            self::split($typeId);

            return true;
        } catch (TypeIDException) {
            return false;
        }
    }

    /**
     * @return array{type:string, uuid:string, time:?DateTimeImmutable}
     */
    public static function parse(string $typeId): array
    {
        [$type, $suffix] = self::split($typeId);
        $bytes = TypeIdCodec::decode($suffix);
        $uuid = UUID::fromBytes($bytes);
        $isUuidV7 = (ord($bytes[6]) >> 4) === 7 && (ord($bytes[8]) & 0xc0) === 0x80;

        return [
            'type' => $type,
            'uuid' => $uuid,
            'time' => $isUuidV7 ? self::uuidV7Time($bytes) : null,
        ];
    }

    public static function toUuid(string $typeId): string
    {
        [, $suffix] = self::split($typeId);

        return UUID::fromBytes(TypeIdCodec::decode($suffix));
    }

    private static function assertPrefix(string $type): void
    {
        $length = strlen($type);
        if ($length > 63 || ($type !== '' && preg_match('/^[a-z](?:[a-z_]*[a-z])?$/D', $type) !== 1)) {
            throw new TypeIDException('TypeID prefix must contain 0..63 lowercase letters or underscores and start/end with a letter');
        }
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function split(string $typeId): array
    {
        if (strlen($typeId) < 26) {
            throw new TypeIDException('TypeID must contain a 26-character suffix');
        }

        $suffix = substr($typeId, -26);
        $type = substr($typeId, 0, -26);
        if ($type !== '') {
            if (!str_ends_with($type, '_')) {
                throw new TypeIDException('TypeID prefix must be separated from its suffix');
            }

            $type = substr($type, 0, -1);
            if ($type === '') {
                throw new TypeIDException('An empty TypeID prefix must omit the separator');
            }
        }

        self::assertPrefix($type);

        try {
            TypeIdCodec::decode($suffix);
        } catch (\InvalidArgumentException $exception) {
            throw new TypeIDException($exception->getMessage(), 0, $exception);
        }

        return [$type, $suffix];
    }

    private static function uuidV7Time(string $bytes): DateTimeImmutable
    {
        $milliseconds = 0;
        for ($index = 0; $index < 6; ++$index) {
            $milliseconds = ($milliseconds << 8) | ord($bytes[$index]);
        }

        return new DateTimeImmutable(
            '@'
            . intdiv($milliseconds, 1000)
            . '.'
            . str_pad((string) (($milliseconds % 1000) * 1000), 6, '0', STR_PAD_LEFT),
        );
    }
}
