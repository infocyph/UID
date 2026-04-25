<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeInterface;
use Exception;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\Enums\UlidGenerationMode;
use Infocyph\UID\Value\SnowflakeValue;
use Infocyph\UID\Value\SonyflakeValue;
use Infocyph\UID\Value\TbslValue;
use Infocyph\UID\Value\UlidValue;
use Infocyph\UID\Value\UuidValue;

final class Id
{
    /**
     * @throws Exception
     */
    public static function cuid2(int $maxLength = 24): string
    {
        return CUID2::generate($maxLength);
    }

    public static function cuid2IsValid(string $id): bool
    {
        return CUID2::isValid($id);
    }

    /**
     * @return array{isValid: bool, length: int}
     */
    public static function cuid2Parse(string $id): array
    {
        return CUID2::parse($id);
    }

    public static function deterministic(string $payload, int $length = 24, string $namespace = 'default'): string
    {
        return DeterministicId::fromPayload($payload, $length, $namespace);
    }

    /**
     * @throws Exception
     */
    public static function ksuid(?DateTimeInterface $dateTime = null): string
    {
        return KSUID::generate($dateTime);
    }

    /**
     * @throws Exception
     */
    public static function nanoId(int $size = 21): string
    {
        return NanoID::generate($size);
    }

    public static function nanoIdIsValid(string $id, ?int $size = null): bool
    {
        return NanoID::isValid($id, $size);
    }

    /**
     * @return array{isValid: bool, length: int, alphabet: string}
     */
    public static function nanoIdParse(string $id, ?int $size = null): array
    {
        return NanoID::parse($id, $size);
    }

    /**
     * @throws Exception
     */
    public static function opaque(int $length = 12): string
    {
        return OpaqueId::random($length);
    }

    /**
     * @throws Exception
     */
    public static function snowflake(?SnowflakeConfig $config = null): int|string
    {
        if ($config === null) {
            return Snowflake::generate();
        }

        return Snowflake::generateWithConfig($config);
    }

    /**
     * @throws Exception
     */
    public static function snowflakeValue(?SnowflakeConfig $config = null): SnowflakeValue
    {
        return new SnowflakeValue((string) self::snowflake($config));
    }

    /**
     * @throws Exception
     */
    public static function sonyflake(?SonyflakeConfig $config = null): int|string
    {
        if ($config === null) {
            return Sonyflake::generate();
        }

        return Sonyflake::generateWithConfig($config);
    }

    /**
     * @throws Exception
     */
    public static function sonyflakeValue(?SonyflakeConfig $config = null): SonyflakeValue
    {
        return new SonyflakeValue((string) self::sonyflake($config));
    }

    /**
     * @throws Exception
     */
    public static function tbsl(?TBSLConfig $config = null): int|string
    {
        if ($config === null) {
            return TBSL::generate();
        }

        return TBSL::generateWithConfig($config);
    }

    /**
     * @throws Exception
     */
    public static function tbslValue(?TBSLConfig $config = null): TbslValue
    {
        return new TbslValue((string) self::tbsl($config));
    }

    /**
     * @throws Exception
     */
    public static function ulid(
        ?DateTimeInterface $dateTime = null,
        UlidGenerationMode $mode = UlidGenerationMode::MONOTONIC,
    ): string {
        return ULID::generate($dateTime, $mode);
    }

    /**
     * @throws Exception
     */
    public static function ulidValue(
        ?DateTimeInterface $dateTime = null,
        UlidGenerationMode $mode = UlidGenerationMode::MONOTONIC,
    ): UlidValue {
        return new UlidValue(self::ulid($dateTime, $mode));
    }

    /**
     * Default UUID strategy (v7).
     *
     * @throws Exception
     */
    public static function uuid(?DateTimeInterface $dateTime = null, ?string $node = null): string
    {
        return self::uuid7($dateTime, $node);
    }

    /**
     * @throws Exception
     */
    public static function uuid1(?string $node = null): string
    {
        return UUID::v1($node);
    }

    /**
     * @throws Exception
     */
    public static function uuid1Value(?string $node = null): UuidValue
    {
        return new UuidValue(self::uuid1($node));
    }

    /**
     * @throws Exception
     */
    public static function uuid3(string $namespace, string $string): string
    {
        return UUID::v3($namespace, $string);
    }

    /**
     * @throws Exception
     */
    public static function uuid3Value(string $namespace, string $string): UuidValue
    {
        return new UuidValue(self::uuid3($namespace, $string));
    }

    /**
     * @throws Exception
     */
    public static function uuid4(): string
    {
        return UUID::v4();
    }

    /**
     * @throws Exception
     */
    public static function uuid4Value(): UuidValue
    {
        return new UuidValue(self::uuid4());
    }

    /**
     * @throws Exception
     */
    public static function uuid5(string $namespace, string $string): string
    {
        return UUID::v5($namespace, $string);
    }

    /**
     * @throws Exception
     */
    public static function uuid5Value(string $namespace, string $string): UuidValue
    {
        return new UuidValue(self::uuid5($namespace, $string));
    }

    /**
     * @throws Exception
     */
    public static function uuid6(?string $node = null): string
    {
        return UUID::v6($node);
    }

    /**
     * @throws Exception
     */
    public static function uuid6Value(?string $node = null): UuidValue
    {
        return new UuidValue(self::uuid6($node));
    }

    /**
     * @throws Exception
     */
    public static function uuid7(?DateTimeInterface $dateTime = null, ?string $node = null): string
    {
        return UUID::v7($dateTime, $node);
    }

    /**
     * @throws Exception
     */
    public static function uuid7Value(?DateTimeInterface $dateTime = null, ?string $node = null): UuidValue
    {
        return new UuidValue(self::uuid7($dateTime, $node));
    }

    /**
     * @throws Exception
     */
    public static function uuid8(?string $node = null): string
    {
        return UUID::v8($node);
    }

    /**
     * @throws Exception
     */
    public static function uuid8Value(?string $node = null): UuidValue
    {
        return new UuidValue(self::uuid8($node));
    }

    /**
     * @throws Exception
     */
    public static function uuidBraces(string $uuid): string
    {
        return UUID::toBraces($uuid);
    }

    /**
     * @throws Exception
     */
    public static function uuidCompact(string $uuid): string
    {
        return UUID::compact($uuid);
    }

    /**
     * @throws Exception
     */
    public static function uuidFromBase(string $encoded, int $base): string
    {
        return UUID::fromBase($encoded, $base);
    }

    /**
     * @throws Exception
     */
    public static function uuidFromBytes(string $bytes): string
    {
        return UUID::fromBytes($bytes);
    }

    public static function uuidIsMax(string $uuid): bool
    {
        return UUID::isMax($uuid);
    }

    public static function uuidIsNil(string $uuid): bool
    {
        return UUID::isNil($uuid);
    }

    public static function uuidIsValid(string $uuid): bool
    {
        return UUID::isValid($uuid);
    }

    public static function uuidMax(): string
    {
        return UUID::max();
    }

    public static function uuidNil(): string
    {
        return UUID::nil();
    }

    /**
     * @throws Exception
     */
    public static function uuidNormalize(string $uuid): string
    {
        return UUID::normalize($uuid);
    }

    /**
     * @return array{isValid: bool, version: int|null, variant: string|null, time: \DateTimeInterface|null, node: string|null, tail: string|null}
     * @throws Exception
     */
    public static function uuidParse(string $uuid): array
    {
        return UUID::parse($uuid);
    }

    /**
     * @throws Exception
     */
    public static function uuidToBase(string $uuid, int $base): string
    {
        return UUID::toBase($uuid, $base);
    }

    /**
     * @throws Exception
     */
    public static function uuidToBytes(string $uuid): string
    {
        return UUID::toBytes($uuid);
    }

    /**
     * @throws Exception
     */
    public static function uuidUrn(string $uuid): string
    {
        return UUID::toUrn($uuid);
    }

    public static function uuidValue(string $uuid): UuidValue
    {
        return new UuidValue($uuid);
    }

    /**
     * @throws Exception
     */
    public static function xid(): string
    {
        return XID::generate();
    }
}
