<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeInterface;
use Infocyph\UID\Configuration\RandflakeConfig;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\Enums\UlidGenerationMode;

function cuid2(int $length = 24): string
{
    return CUID2::generate($length);
}

function ksuid(?DateTimeInterface $dateTime = null): string
{
    return KSUID::generate($dateTime);
}

function nano_id(int $length = 21): string
{
    return NanoID::generate($length);
}

function object_id(?DateTimeInterface $dateTime = null): string
{
    return ObjectID::generate($dateTime);
}

function randflake(RandflakeConfig $config): string
{
    return Randflake::generateWithConfig($config);
}

function random_id(int $length = 21, string $alphabet = RandomId::DEFAULT_ALPHABET): string
{
    return RandomId::generate($length, $alphabet);
}

function snowflake(?SnowflakeConfig $config = null): string
{
    return $config === null ? Snowflake::generate() : Snowflake::generateWithConfig($config);
}

function sonyflake(?SonyflakeConfig $config = null): string
{
    return $config === null ? Sonyflake::generate() : Sonyflake::generateWithConfig($config);
}

function tbsl(?TBSLConfig $config = null): string
{
    return $config === null ? TBSL::generate() : TBSL::generateWithConfig($config);
}

function type_id(string $type = ''): string
{
    return TypeID::generate($type);
}

function ulid(
    ?DateTimeInterface $dateTime = null,
    UlidGenerationMode $mode = UlidGenerationMode::MONOTONIC,
): string {
    return ULID::generate($dateTime, $mode);
}

function uuid1(?string $node = null): string
{
    return UUID::v1($node);
}

function uuid3(string $namespace, string $string): string
{
    return UUID::v3($namespace, $string);
}

function uuid4(): string
{
    return UUID::v4();
}

function uuid5(string $namespace, string $string): string
{
    return UUID::v5($namespace, $string);
}

function uuid6(?string $node = null): string
{
    return UUID::v6($node);
}

function uuid7(?DateTimeInterface $dateTime = null): string
{
    return UUID::v7($dateTime);
}

function uuid8(?string $node = null): string
{
    return UUID::v8($node);
}

function xid(): string
{
    return XID::generate();
}
