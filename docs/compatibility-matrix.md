# Compatibility Matrix

## UUID support

- `v1`, `v3`, `v4`, `v5`: RFC 4122 / RFC 9562 compatible layouts.
- `v6`, `v7`: RFC 9562 time-ordered UUIDs.
- `v8`: custom payload strategy inside RFC 9562 v8 envelope.
- `guid()`: Microsoft-compatible GUID text formatting helper.

## Non-UUID families

- `ULID`: Crockford base32 ULID (monotonic + random generation modes).
- `Snowflake`: 64-bit Twitter-style ID (41/5/5/12 layout).
- `Sonyflake`: 64-bit Sonyflake-style ID (39/16/8 layout).
- `TBSL`: project-specific time-based sortable hex identifier.
- `NanoID`, `CUID2`: random ID families for URL-safe usage.

## Binary and alternate representations

- UUID / ULID / TBSL: `toBytes()` / `fromBytes()`.
- UUID / ULID / TBSL / Snowflake / Sonyflake: base encodings via `toBase()` / `fromBase()` where applicable.

## Runtime

- Minimum PHP: `8.2`.
- Required extension: `ext-bcmath`.
- Optional sequence backends: filesystem (default), in-memory, PSR-16 simple cache, callback.
