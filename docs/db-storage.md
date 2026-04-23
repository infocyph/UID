# Sortable DB Helpers

## UUID

- MySQL: prefer `BINARY(16)` for compact indexes.
- PostgreSQL: prefer native `UUID` type.
- Ordering: use UUIDv7 when insertion locality matters.

## ULID

- MySQL: `CHAR(26)` (human-friendly) or `BINARY(16)` (compact).
- PostgreSQL: `CHAR(26)` or `BYTEA`.
- Ordering: canonical ULID text preserves chronological order.

## Snowflake / Sonyflake

- MySQL: `BIGINT UNSIGNED`.
- PostgreSQL: `BIGINT` if in range, otherwise `NUMERIC(20,0)`.
- Ordering: numeric sort is time sort.

## TBSL

- Store as fixed-length uppercase hex: `CHAR(20)`.
- If compactness matters, store bytes in `BINARY(10)` via `toBytes()`.
