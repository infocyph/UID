Value Objects and Comparison
============================

Value objects implement ``Infocyph\UID\Contracts\IdValueInterface`` and expose
the canonical string, comparison, timestamp, machine, version, and sortability
metadata appropriate to their format.

``SnowflakeValue`` and ``SonyflakeValue`` retain an optional custom epoch. Prefer
the matching ``Id::snowflakeValue($config)`` or ``Id::sonyflakeValue($config)``
factory so generated values are parsed in their original epoch domain.

``UuidValue::isSortable()`` is true only for UUIDv6 and UUIDv7. A generic UUIDv8
value does not infer timestamp or sortable semantics.

``IdComparator`` compares digit-only IDs as unsigned decimal values and otherwise
uses lexical ordering.
