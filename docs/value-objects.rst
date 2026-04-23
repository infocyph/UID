Value Objects and Comparison
============================

Interface
---------

All value objects implement ``Infocyph\\UID\\Contracts\\IdValueInterface``:

- ``toString()``
- ``compare(IdValueInterface|string $other)``
- ``getTimestamp(): ?DateTimeImmutable``
- ``getMachineId(): int|string|null``
- ``getVersion(): ?int``
- ``isSortable(): bool``

Available Value Classes
-----------------------

- ``Infocyph\\UID\\Value\\UuidValue``
- ``Infocyph\\UID\\Value\\UlidValue``
- ``Infocyph\\UID\\Value\\SnowflakeValue``
- ``Infocyph\\UID\\Value\\SonyflakeValue``
- ``Infocyph\\UID\\Value\\TbslValue``

Using Value Objects
-------------------

.. code-block:: php

   <?php

   use Infocyph\UID\Id;

   $uuid = Id::uuid7Value();
   $ulid = Id::ulidValue();
   $snowflake = Id::snowflakeValue();

   $uuidText = $uuid->toString();
   $uuidVersion = $uuid->getVersion();
   $uuidTime = $uuid->getTimestamp();

IdComparator
------------

``Infocyph\\UID\\IdComparator`` provides generic comparison/sorting:

- ``compare(IdValueInterface|string $left, IdValueInterface|string $right): int``
- ``sort(array $ids): array``

When both values are digit-only, comparator uses unsigned-decimal ordering;
otherwise it falls back to lexical comparison.

.. code-block:: php

   <?php

   use Infocyph\UID\IdComparator;

   $sorted = IdComparator::sort(['9', '10', '2']);
   // ['2', '9', '10']
