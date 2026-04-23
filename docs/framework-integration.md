# Framework Integration Notes

## Laravel

- Helper functions are available automatically via Composer autoload.

- Prefer UUIDv7 / ULID for ordered primary keys.
- Keep IDs as strings at app boundary; convert to binary for DB only when needed.

## Symfony

- Helper functions are available automatically via Composer autoload.

- Use `Id` factory in services to centralize generation strategy.

## Generic PHP apps

- Use `Id::nanoId()` for URL-safe public IDs.
- Use configuration objects (`SnowflakeConfig`, `SonyflakeConfig`, `TBSLConfig`) for policy/output tuning.
- Use value objects (`UuidValue`, `UlidValue`, etc.) for safer domain modeling.
- For distributed sequence coordination with PSR-16 caches, pass a shared synchronizer callback to `PsrSimpleCacheSequenceProvider`.
