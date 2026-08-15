<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeInterface;
use Infocyph\UID\Configuration\RandflakeConfig;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\Enums\UlidGenerationMode;
use Infocyph\UID\Value\SnowflakeValue;
use Infocyph\UID\Value\SonyflakeValue;

final class Id
{
    public static function cuid2(int $length = 24): string
    {
        return CUID2::generate($length);
    }

    public static function deterministic(string $payload, int $length = 24, string $namespace = 'default'): string
    {
        return DeterministicId::fromPayload($payload, $length, $namespace);
    }

    public static function ksuid(?DateTimeInterface $dateTime = null): string
    {
        return KSUID::generate($dateTime);
    }

    public static function nanoId(int $length = 21): string
    {
        return NanoID::generate($length);
    }

    public static function objectId(?DateTimeInterface $dateTime = null): string
    {
        return ObjectID::generate($dateTime);
    }

    public static function randflake(RandflakeConfig $config): string
    {
        return Randflake::generateWithConfig($config);
    }

    public static function random(
        int $length = 21,
        string $alphabet = RandomId::DEFAULT_ALPHABET,
    ): string {
        return RandomId::generate($length, $alphabet);
    }

    public static function snowflake(?SnowflakeConfig $config = null): string
    {
        return $config === null ? Snowflake::generate() : Snowflake::generateWithConfig($config);
    }

    public static function snowflakeValue(?SnowflakeConfig $config = null): SnowflakeValue
    {
        return new SnowflakeValue(self::snowflake($config), $config?->resolveCustomEpochMs());
    }

    public static function sonyflake(?SonyflakeConfig $config = null): string
    {
        return $config === null ? Sonyflake::generate() : Sonyflake::generateWithConfig($config);
    }

    public static function sonyflakeValue(?SonyflakeConfig $config = null): SonyflakeValue
    {
        return new SonyflakeValue(self::sonyflake($config), $config?->resolveCustomEpochMs());
    }

    public static function tbsl(?TBSLConfig $config = null): string
    {
        return $config === null ? TBSL::generate() : TBSL::generateWithConfig($config);
    }

    public static function typeId(string $type = ''): string
    {
        return TypeID::generate($type);
    }

    public static function ulid(
        ?DateTimeInterface $dateTime = null,
        UlidGenerationMode $mode = UlidGenerationMode::MONOTONIC,
    ): string {
        return ULID::generate($dateTime, $mode);
    }

    public static function uuid(?DateTimeInterface $dateTime = null): string
    {
        return UUID::v7($dateTime);
    }

    public static function uuid1(?string $node = null): string
    {
        return UUID::v1($node);
    }

    public static function uuid3(string $namespace, string $string): string
    {
        return UUID::v3($namespace, $string);
    }

    public static function uuid4(): string
    {
        return UUID::v4();
    }

    public static function uuid5(string $namespace, string $string): string
    {
        return UUID::v5($namespace, $string);
    }

    public static function uuid6(?string $node = null): string
    {
        return UUID::v6($node);
    }

    public static function uuid7(?DateTimeInterface $dateTime = null): string
    {
        return UUID::v7($dateTime);
    }

    public static function uuid8(?string $node = null): string
    {
        return UUID::v8($node);
    }

    public static function xid(): string
    {
        return XID::generate();
    }
}
