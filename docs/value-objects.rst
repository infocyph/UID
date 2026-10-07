Value Objects and Comparison
============================

Value objects implement ``Infocyph\UID\Contracts\IdValueInterface`` and expose
the canonical string, comparison, timestamp, machine, version, and sortability
metadata appropriate to their format.

``SnowflakeValue`` and ``SonyflakeValue`` retain an optional custom epoch.
``SonyflakeValue`` also retains its explicit ``SonyflakeFormat``. Prefer the
matching ``Id::snowflakeValue($config)`` or ``Id::sonyflakeValue($config)``
factory so generated values are parsed in their original epoch/format domain.

``UuidValue::isSortable()`` is true only for UUIDv6 and UUIDv7. A generic UUIDv8
value does not infer timestamp or sortable semantics.

``IdComparator`` defines a total mixed order: digit-only IDs compare as unsigned
decimal values, textual IDs compare lexically, and numeric IDs sort before text
when the two categories are mixed.
