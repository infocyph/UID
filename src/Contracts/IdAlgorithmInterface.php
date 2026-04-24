<?php

declare(strict_types=1);

namespace Infocyph\UID\Contracts;

interface IdAlgorithmInterface
{
    /**
     * Generates an identifier using the algorithm default options.
     *
     * Implementations may expose additional optional parameters.
     */
    public static function generate(): string;

    /**
     * Checks whether the given identifier is valid for this algorithm.
     */
    public static function isValid(string $id): bool;

    /**
     * Parses algorithm-specific information from the identifier.
     *
     * @return array<string, mixed>
     */
    public static function parse(string $id): array;
}
