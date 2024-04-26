<?php

namespace Infocyph\UID;

use Exception;
use Infocyph\UID\Exceptions\SnowflakeException;
use Infocyph\UID\Exceptions\SonyflakeException;

if (!function_exists('Infocyph\UID\uuid1')) {
    /**
     * Generates a version 1 UUID
     *
     * @param string|null $node The node value to use in the UUID.
     * @return string The generated UUID.
     * @throws Exception
     */
    function uuid1(string $node = null): string
    {
        return UUID::v1($node);
    }
}

if (!function_exists('Infocyph\UID\uuid3')) {
    /**
     * Generate a Version 3 UUID.
     *
     * @param string $string The string to generate the UUID from.
     * @param string $namespace The namespace to use for the UUID generation.
     * @return string The generated UUID.
     * @throws Exception|Exceptions\UUIDException
     */
    function uuid3(string $string, string $namespace): string
    {
        return UUID::v3($string, $namespace);
    }
}

if (!function_exists('Infocyph\UID\uuid4')) {
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

if (!function_exists('Infocyph\UID\uuid5')) {
    /**
     * Generates a version 5 UUID for a given string using the specified namespace.
     *
     * @param string $string The string to generate the UUID from.
     * @param string $namespace The namespace to use for the UUID generation.
     * @return string The generated UUID.
     * @throws Exceptions\UUIDException
     */
    function uuid5(string $string, string $namespace): string
    {
        return UUID::v5($string, $namespace);
    }
}

if (!function_exists('Infocyph\UID\uuid6')) {
    /**
     * Generates a Version 6 UUID.
     *
     * @param string|null $node The node identifier. Defaults to null.
     * @return string
     * @throws Exception
     */
    function uuid6(string $node = null): string
    {
        return UUID::v6($node);
    }
}

if (!function_exists('Infocyph\UID\uuid7')) {
    /**
     * Generates a version 7 UUID.
     *
     * @param string|null $node The node identifier. Defaults to null.
     * @return string
     * @throws Exception
     */
    function uuid7(string $node = null): string
    {
        return UUID::v7($node);
    }
}

if (!function_exists('Infocyph\UID\uuid8')) {
    /**
     * Generates a Version 8 UUID.
     *
     * @param string|null $node The node identifier. Defaults to null.
     * @return string
     * @throws Exception
     */
    function uuid8(string $node = null): string
    {
        return UUID::v8($node);
    }
}

if (!function_exists('Infocyph\UID\ulid')) {
    /**
     * Generates ULID.
     *
     * @return string
     * @throws Exception
     */
    function ulid(): string
    {
        return ULID::generate();
    }
}

if (!function_exists('Infocyph\UID\snowflake')) {
    /**
     * Generates Snowflake ID.
     *
     * @param int $datacenter
     * @param int $workerId
     * @return string
     * @throws SnowflakeException
     */
    function snowflake(int $datacenter = 0, int $workerId = 0): string
    {
        return Snowflake::generate($datacenter, $workerId);
    }
}

if (!function_exists('Infocyph\UID\sonyflake')) {
    /**
     * Generates Sonyflake ID.
     *
     * @param int $machineId
     * @return string
     * @throws SonyflakeException
     */
    function sonyflake(int $machineId = 0): string
    {
        return Sonyflake::generate($machineId);
    }
}

if (!function_exists('Infocyph\UID\tbsl')) {
    /**
     * Generates TBSL ID.
     *
     * @param int $machineId
     * @return string
     * @throws Exception
     */
    function tbsl(int $machineId = 0): string
    {
        return TBSL::generate($machineId);
    }
}
