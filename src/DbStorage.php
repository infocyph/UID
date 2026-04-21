<?php

declare(strict_types=1);

namespace Infocyph\UID;

final class DbStorage
{
    /**
     * Snowflake storage recommendations.
     *
     * @return array{mysql:string, postgres:string, ordering:string}
     */
    public static function snowflake(): array
    {
        return [
            'mysql' => 'Use BIGINT UNSIGNED for numeric operations and compact indexing.',
            'postgres' => 'Use BIGINT when value fits signed range, otherwise NUMERIC(20,0).',
            'ordering' => 'Snowflake IDs are time-sortable by numeric order.',
        ];
    }

    /**
     * ULID storage recommendations.
     *
     * @return array{mysql:string, postgres:string, ordering:string}
     */
    public static function ulid(): array
    {
        return [
            'mysql' => 'Use CHAR(26) for readability or BINARY(16) when compactness/performance is primary.',
            'postgres' => 'Use CHAR(26) or BYTEA depending on interoperability needs.',
            'ordering' => 'ULID lexical order preserves chronological order in canonical 26-char text.',
        ];
    }

    /**
     * UUID storage recommendations.
     *
     * @return array{mysql:string, postgres:string, ordering:string}
     */
    public static function uuid(): array
    {
        return [
            'mysql' => 'Use BINARY(16) for compact storage; keep generated columns for textual debugging if needed.',
            'postgres' => 'Use native UUID type. It is space-efficient and indexed well.',
            'ordering' => 'UUIDv7 provides better temporal locality for B-Tree indexes than v4.',
        ];
    }
}
