<?php

declare(strict_types=1);

use Infocyph\UID\CUID2;
use Infocyph\UID\DeterministicId;
use Infocyph\UID\Enums\UlidGenerationMode;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SnowflakeException;
use Infocyph\UID\Exceptions\SonyflakeException;
use Infocyph\UID\KSUID;
use Infocyph\UID\NanoID;
use Infocyph\UID\OpaqueId;
use Infocyph\UID\Snowflake;
use Infocyph\UID\Sonyflake;
use Infocyph\UID\TBSL;
use Infocyph\UID\ULID;
use Infocyph\UID\UUID;
use Infocyph\UID\XID;

if (!function_exists('uuid1')) {
    /**
     * Generates a version 1 UUID
     *
     * @param string|null $node The node value to use in the UUID.
     * @return string The generated UUID.
     * @throws Exception
     */
    function uuid1(?string $node = null): string
    {
        return UUID::v1($node);
    }
}

if (!function_exists('ksuid')) {
    /**
     * Generates KSUID.
     *
     * @throws Exception
     */
    function ksuid(?DateTimeInterface $dateTime = null): string
    {
        return KSUID::generate($dateTime);
    }
}

if (!function_exists('xid')) {
    /**
     * Generates XID.
     *
     * @throws Exception
     */
    function xid(): string
    {
        return XID::generate();
    }
}

if (!function_exists('uuid3')) {
    /**
     * Generate a Version 3 UUID.
     *
     * @param string $namespace The namespace to use for the UUID generation.
     * @param string $string The string to generate the UUID from.
     * @return string The generated UUID.
     * @throws Exception
     */
    function uuid3(string $namespace, string $string): string
    {
        return UUID::v3($namespace, $string);
    }
}

if (!function_exists('uuid4')) {
    /**
     * Generates a version 4 UUID.
     *
     * @return string The generated UUID.
     * @throws Exception
     */
    function uuid4(): string
    {
        return UUID::v4();
    }
}

if (!function_exists('uuid5')) {
    /**
     * Generate a Version 5 UUID.
     *
     * @param string $namespace The namespace to use for the UUID generation.
     * @param string $string The string to generate the UUID from.
     * @return string The generated UUID.
     * @throws Exception
     */
    function uuid5(string $namespace, string $string): string
    {
        return UUID::v5($namespace, $string);
    }
}

if (!function_exists('uuid6')) {
    /**
     * Generates a Version 6 UUID.
     *
     * @param string|null $node The node identifier. Defaults to null.
     * @throws Exception
     */
    function uuid6(?string $node = null): string
    {
        return UUID::v6($node);
    }
}

if (!function_exists('uuid7')) {
    /**
     * Generates a version 7 UUID.
     *
     * @param DateTimeInterface|null $dateTime An optional DateTimeInterface object to create the UUID.
     * @param string|null $node The node identifier. Defaults to null.
     * @throws Exception
     */
    function uuid7(?DateTimeInterface $dateTime = null, ?string $node = null): string
    {
        return UUID::v7($dateTime, $node);
    }
}

if (!function_exists('uuid8')) {
    /**
     * Generates a Version 8 UUID.
     *
     * @param string|null $node The node identifier. Defaults to null.
     * @throws Exception
     */
    function uuid8(?string $node = null): string
    {
        return UUID::v8($node);
    }
}

if (!function_exists('uuid_nil')) {
    /**
     * Returns UUID NIL value.
     */
    function uuid_nil(): string
    {
        return UUID::nil();
    }
}

if (!function_exists('uuid_max')) {
    /**
     * Returns UUID MAX value.
     */
    function uuid_max(): string
    {
        return UUID::max();
    }
}

if (!function_exists('uuid_is_nil')) {
    /**
     * Checks whether the UUID is NIL.
     */
    function uuid_is_nil(string $uuid): bool
    {
        return UUID::isNil($uuid);
    }
}

if (!function_exists('uuid_is_max')) {
    /**
     * Checks whether the UUID is MAX.
     */
    function uuid_is_max(string $uuid): bool
    {
        return UUID::isMax($uuid);
    }
}

if (!function_exists('uuid_normalize')) {
    /**
     * Normalizes UUID to canonical lowercase format.
     *
     * @throws Exception
     */
    function uuid_normalize(string $uuid): string
    {
        return UUID::normalize($uuid);
    }
}

if (!function_exists('uuid_compact')) {
    /**
     * Converts UUID to compact format.
     *
     * @throws Exception
     */
    function uuid_compact(string $uuid): string
    {
        return UUID::compact($uuid);
    }
}

if (!function_exists('uuid_urn')) {
    /**
     * Converts UUID to URN format.
     *
     * @throws Exception
     */
    function uuid_urn(string $uuid): string
    {
        return UUID::toUrn($uuid);
    }
}

if (!function_exists('uuid_braces')) {
    /**
     * Converts UUID to brace format.
     *
     * @throws Exception
     */
    function uuid_braces(string $uuid): string
    {
        return UUID::toBraces($uuid);
    }
}

if (!function_exists('uuid_to_base')) {
    /**
     * Encodes UUID into base16/base32/base36/base58/base62.
     *
     * @throws Exception
     */
    function uuid_to_base(string $uuid, int $base): string
    {
        return UUID::toBase($uuid, $base);
    }
}

if (!function_exists('uuid_from_base')) {
    /**
     * Decodes UUID from base16/base32/base36/base58/base62.
     *
     * @throws Exception
     */
    function uuid_from_base(string $encoded, int $base): string
    {
        return UUID::fromBase($encoded, $base);
    }
}

if (!function_exists('guid')) {
    /**
     * Generates a GUID (Globally Unique Identifier) string.
     *
     * @param bool $trim Whether to trim the curly braces from the GUID string. Default is true.
     * @return string The generated GUID string.
     * @throws Exception
     */
    function guid(bool $trim = true): string
    {
        return UUID::guid($trim);
    }
}

if (!function_exists('ulid')) {
    /**
     * Generates ULID.
     *
     * @throws Exception
     */
    function ulid(?DateTimeInterface $dateTime = null): string
    {
        return ULID::generate($dateTime);
    }
}

if (!function_exists('ulid_monotonic')) {
    /**
     * Generates monotonic ULID.
     *
     * @throws Exception
     */
    function ulid_monotonic(?DateTimeInterface $dateTime = null): string
    {
        return ULID::generate($dateTime, UlidGenerationMode::MONOTONIC);
    }
}

if (!function_exists('ulid_random')) {
    /**
     * Generates strict-random ULID.
     *
     * @throws Exception
     */
    function ulid_random(?DateTimeInterface $dateTime = null): string
    {
        return ULID::generate($dateTime, UlidGenerationMode::RANDOM);
    }
}

if (!function_exists('ulid_to_base')) {
    /**
     * Encodes ULID into base16/base32/base36/base58/base62.
     *
     * @throws Exception
     */
    function ulid_to_base(string $ulid, int $base): string
    {
        return ULID::toBase($ulid, $base);
    }
}

if (!function_exists('ulid_from_base')) {
    /**
     * Decodes ULID from base16/base32/base36/base58/base62.
     *
     * @throws Exception
     */
    function ulid_from_base(string $encoded, int $base): string
    {
        return ULID::fromBase($encoded, $base);
    }
}

if (!function_exists('snowflake')) {
    /**
     * Generates Snowflake ID.
     *
     * @throws SnowflakeException|FileLockException
     */
    function snowflake(int $datacenter = 0, int $workerId = 0): string
    {
        return Snowflake::generate($datacenter, $workerId);
    }
}

if (!function_exists('snowflake_is_valid')) {
    /**
     * Checks whether Snowflake ID is valid.
     */
    function snowflake_is_valid(string $id): bool
    {
        return Snowflake::isValid($id);
    }
}

if (!function_exists('snowflake_to_base')) {
    /**
     * Encodes Snowflake into base16/base32/base36/base58/base62.
     *
     * @throws Exception
     */
    function snowflake_to_base(string $id, int $base): string
    {
        return Snowflake::toBase($id, $base);
    }
}

if (!function_exists('snowflake_from_base')) {
    /**
     * Decodes Snowflake from base16/base32/base36/base58/base62.
     *
     * @throws Exception
     */
    function snowflake_from_base(string $encoded, int $base): string
    {
        return Snowflake::fromBase($encoded, $base);
    }
}

if (!function_exists('sonyflake')) {
    /**
     * Generates Sonyflake ID.
     *
     * @throws SonyflakeException|FileLockException
     */
    function sonyflake(int $machineId = 0): string
    {
        return Sonyflake::generate($machineId);
    }
}

if (!function_exists('sonyflake_is_valid')) {
    /**
     * Checks whether Sonyflake ID is valid.
     */
    function sonyflake_is_valid(string $id): bool
    {
        return Sonyflake::isValid($id);
    }
}

if (!function_exists('sonyflake_to_base')) {
    /**
     * Encodes Sonyflake into base16/base32/base36/base58/base62.
     *
     * @throws Exception
     */
    function sonyflake_to_base(string $id, int $base): string
    {
        return Sonyflake::toBase($id, $base);
    }
}

if (!function_exists('sonyflake_from_base')) {
    /**
     * Decodes Sonyflake from base16/base32/base36/base58/base62.
     *
     * @throws Exception
     */
    function sonyflake_from_base(string $encoded, int $base): string
    {
        return Sonyflake::fromBase($encoded, $base);
    }
}

if (!function_exists('tbsl')) {
    /**
     * Generates TBSL ID.
     *
     * @throws Exception
     */
    function tbsl(int $machineId = 0, bool $sequenced = false): string
    {
        return TBSL::generate($machineId, $sequenced);
    }
}

if (!function_exists('tbsl_is_valid')) {
    /**
     * Checks whether TBSL ID is valid.
     */
    function tbsl_is_valid(string $id): bool
    {
        return TBSL::isValid($id);
    }
}

if (!function_exists('tbsl_to_base')) {
    /**
     * Encodes TBSL into base16/base32/base36/base58/base62.
     *
     * @throws Exception
     */
    function tbsl_to_base(string $id, int $base): string
    {
        return TBSL::toBase($id, $base);
    }
}

if (!function_exists('tbsl_from_base')) {
    /**
     * Decodes TBSL from base16/base32/base36/base58/base62.
     *
     * @throws Exception
     */
    function tbsl_from_base(string $encoded, int $base): string
    {
        return TBSL::fromBase($encoded, $base);
    }
}

if (!function_exists('nanoid')) {
    /**
     * Generates Nano ID.
     *
     * @throws Exception
     */
    function nanoid(int $size = 21): string
    {
        return NanoID::generate($size);
    }
}

if (!function_exists('nanoid_is_valid')) {
    /**
     * Checks whether NanoID string is valid.
     */
    function nanoid_is_valid(string $id, ?int $size = null): bool
    {
        return NanoID::isValid($id, $size);
    }
}

if (!function_exists('cuid2')) {
    /**
     * Generates CUID2.
     *
     * @throws Exception
     */
    function cuid2(int $maxLength = 24): string
    {
        return CUID2::generate($maxLength);
    }
}

if (!function_exists('cuid2_is_valid')) {
    /**
     * Checks whether CUID2 string is valid.
     */
    function cuid2_is_valid(string $id): bool
    {
        return CUID2::isValid($id);
    }
}

if (!function_exists('opaque_id')) {
    /**
     * Generates short opaque random ID.
     *
     * @throws Exception
     */
    function opaque_id(int $length = 12): string
    {
        return OpaqueId::random($length);
    }
}

if (!function_exists('deterministic_id')) {
    /**
     * Generates deterministic ID from payload.
     */
    function deterministic_id(string $payload, int $length = 24, string $namespace = 'default'): string
    {
        return DeterministicId::fromPayload($payload, $length, $namespace);
    }
}
