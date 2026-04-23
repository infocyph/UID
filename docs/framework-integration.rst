Framework Integration
=====================

Laravel
-------

- Helper functions are available through Composer autoload.
- Prefer UUIDv7 or ULID for ordered primary keys.
- Keep IDs as strings in application boundaries; convert to binary only at persistence edges.

Symfony
-------

- Helper functions are available through Composer autoload.
- Prefer central generation through ``Infocyph\\UID\\Id`` inside services.

Generic PHP Apps
----------------

- Use ``Id::nanoId()`` for short public IDs.
- Use ``Id::deterministic()`` for stable IDs from payloads.
- Use config objects for policy/output tuning:

  - ``SnowflakeConfig``
  - ``SonyflakeConfig``
  - ``TBSLConfig``

- Use value objects for richer domain models:

  - ``UuidValue``
  - ``UlidValue``
  - ``SnowflakeValue``
  - ``SonyflakeValue``
  - ``TbslValue``

Distributed Sequence Coordination
---------------------------------

For PSR-16 cache providers in distributed environments, use
``PsrSimpleCacheSequenceProvider`` with a synchronizer callback backed by a distributed lock.
