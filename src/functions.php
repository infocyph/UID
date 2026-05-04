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

if (!function_exists('__uid_base_call')) {
    function __uid_base_call(string $family, string $method, string $value, int $base): string
    {
        /** @var array<string, array<string, callable(string, int):string>> $operations */
        $operations = [
            'toBase' => [
                'uuid' => UUID::toBase(...),
                'ulid' => ULID::toBase(...),
                'snowflake' => Snowflake::toBase(...),
                'sonyflake' => Sonyflake::toBase(...),
                'tbsl' => TBSL::toBase(...),
            ],
            'fromBase' => [
                'uuid' => UUID::fromBase(...),
                'ulid' => ULID::fromBase(...),
                'snowflake' => Snowflake::fromBase(...),
                'sonyflake' => Sonyflake::fromBase(...),
                'tbsl' => TBSL::fromBase(...),
            ],
        ];

        $familyHandlers = $operations[$method] ?? throw new InvalidArgumentException('Unsupported base operation');
        $handler = $familyHandlers[$family] ?? throw new InvalidArgumentException('Unsupported ID family');

        return $handler($value, $base);
    }
}

if (!function_exists('__uid_is_valid')) {
    function __uid_is_valid(string $family, string $id): bool
    {
        return match ($family) {
            'snowflake' => Snowflake::isValid($id),
            'sonyflake' => Sonyflake::isValid($id),
            'tbsl' => TBSL::isValid($id),
            default => throw new InvalidArgumentException('Unsupported ID family for validation'),
        };
    }
}

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
    function uuid_urn(string $uuid): string
    {
        return UUID::toUrn($uuid);
    }
}
if (!function_exists('uuid_braces')) {
    function uuid_braces(string $uuid): string
    {
        return UUID::toBraces($uuid);
    }
}
if (!function_exists('uuid_to_base')) {
    function uuid_to_base(string $uuid, int $base): string
    {
        return UUID::toBase($uuid, $base);
    }
}
if (!function_exists('uuid_from_base')) {
    function uuid_from_base(string $encoded, int $base): string
    {
        return UUID::fromBase($encoded, $base);
    }
}
if (!function_exists('guid')) {
    function guid(bool $trim = true): string
    {
        return UUID::guid($trim);
    }
}

if (!function_exists('ulid')) {
    function ulid(?DateTimeInterface $dateTime = null): string
    {
        return ULID::generate($dateTime);
    }
}
if (!function_exists('ulid_monotonic')) {
    function ulid_monotonic(?DateTimeInterface $dateTime = null): string
    {
        return ULID::generate($dateTime, UlidGenerationMode::MONOTONIC);
    }
}
if (!function_exists('ulid_random')) {
    function ulid_random(?DateTimeInterface $dateTime = null): string
    {
        return ULID::generate($dateTime, UlidGenerationMode::RANDOM);
    }
}
if (!function_exists('ulid_to_base')) {
    function ulid_to_base(string $ulid, int $base): string
    {
        return __uid_base_call('ulid', 'toBase', $ulid, $base);
    }
}
if (!function_exists('ulid_from_base')) {
    function ulid_from_base(string $encoded, int $base): string
    {
        return __uid_base_call('ulid', 'fromBase', $encoded, $base);
    }
}

if (!function_exists('snowflake')) {
    /** @throws SnowflakeException|FileLockException */
    function snowflake(int $datacenter = 0, int $workerId = 0): string
    {
        return Snowflake::generate($datacenter, $workerId);
    }
}
if (!function_exists('sonyflake')) {
    /** @throws SonyflakeException|FileLockException */
    function sonyflake(int $machineId = 0): string
    {
        return Sonyflake::generate($machineId);
    }
}
if (!function_exists('tbsl')) {
    function tbsl(int $machineId = 0, bool $sequenced = false): string
    {
        return TBSL::generate($machineId, $sequenced);
    }
}

if (!function_exists('snowflake_is_valid')) {
    function snowflake_is_valid(string $id): bool
    {
        return Snowflake::isValid($id);
    }
}
if (!function_exists('sonyflake_is_valid')) {
    function sonyflake_is_valid(string $id): bool
    {
        return __uid_is_valid('sonyflake', $id);
    }
}
if (!function_exists('tbsl_is_valid')) {
    function tbsl_is_valid(string $id): bool
    {
        if ($id === '') {
            return false;
        }

        return __uid_is_valid('tbsl', $id);
    }
}

if (!function_exists('snowflake_to_base')) {
    function snowflake_to_base(string $id, int $base): string
    {
        return Snowflake::toBase($id, $base);
    }
}
if (!function_exists('snowflake_from_base')) {
    function snowflake_from_base(string $encoded, int $base): string
    {
        return Snowflake::fromBase($encoded, $base);
    }
}

if (!function_exists('sonyflake_to_base')) {
    function sonyflake_to_base(string $id, int $base): string
    {
        return __uid_base_call('sonyflake', 'toBase', $id, $base);
    }
}
if (!function_exists('sonyflake_from_base')) {
    function sonyflake_from_base(string $encoded, int $base): string
    {
        return __uid_base_call('sonyflake', 'fromBase', $encoded, $base);
    }
}

if (!function_exists('tbsl_to_base')) {
    function tbsl_to_base(string $id, int $base): string
    {
        $family = 'tbsl';

        return __uid_base_call($family, 'toBase', $id, $base);
    }
}
if (!function_exists('tbsl_from_base')) {
    function tbsl_from_base(string $encoded, int $base): string
    {
        $method = 'fromBase';

        return __uid_base_call('tbsl', $method, $encoded, $base);
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
