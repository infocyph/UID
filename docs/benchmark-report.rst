v5 Benchmark Report
===================

Recorded on 2026-08-15 with PHP 8.4.24, PHPBench 1.7.0, Xdebug disabled,
and OPcache disabled. Each result below is the mode of five iterations.
Generator methods use 1000 revolutions; filesystem methods use 250 or 500.

==========================  ==========
Subject                     Mode
==========================  ==========
UUID v4                     1.782 us
UUID v7                     2.067 us
ULID random                 3.726 us
ULID monotonic              2.203 us
TypeID                      17.063 us
ObjectID                    0.662 us
NanoID                      0.777 us
CUID2                       178.307 us
RandomId                    4.358 us
KSUID                       18.894 us
XID                         10.094 us
Snowflake filesystem        15.606 us
Sonyflake filesystem        16.515 us
Randflake filesystem        25.242 us
TBSL filesystem             15.823 us
DeterministicId             2.831 us
OpaqueId encode/decode      2.337 / 1.683 us
==========================  ==========

Provider Isolation
------------------

==========================  ==========
Subject                     Mode
==========================  ==========
Snowflake filesystem        15.562 us
Snowflake in-memory         2.355 us
Randflake filesystem        24.493 us
Randflake in-memory         11.628 us
==========================  ==========

Native Codec, 16 Bytes
----------------------

The checked-in codec harness covers 8, 10, 12, 16, 20, and 32-byte inputs for
each base. The 16-byte slice is shown here for a compact release baseline.

========  ==========
Base      Mode
========  ==========
16        0.287 us
32        15.113 us
36        14.452 us
58        13.079 us
62        13.026 us
decimal   22.236 us
========  ==========

The former generation harness constructed closures and dispatch arrays inside
the measured operation, and the former sequence harness constructed providers
inside it. Those measurements are not comparable algorithm baselines, so no
misleading percentage delta is reported. This report is the first isolated v5
baseline; future releases should compare against it using the checked-in harness.

Correctness gates took priority over throughput: the multi-process test suite
for Snowflake, Sonyflake, Randflake, TBSL, and sequence reservations produced
zero duplicates and zero lock errors.

Filesystem Contention
---------------------

``ContentionMatrix`` ran 200 allocations per process for 1, 2, 4, 8, and 16
processes and reservation sizes 1, 4, 8, 16, 32, and 64. All 30 cases produced
zero duplicates and zero lock errors. Representative endpoints are:

=========  ===========  ========  ==========  ========  ========  ======
Processes  Reservation  IDs/sec   Median us   p95 us    p99 us    CPU ms
=========  ===========  ========  ==========  ========  ========  ======
1          1            3,697     10.23       16.40     23.21     2.48
1          64           3,865     0.59        0.84      13.34     0.39
2          1            8,916     16.97       27.32     53.38     7.81
2          64           10,012    0.58        0.74      13.69     0.74
4          1            19,639    10.65       25.57     53.91     10.53
4          64           21,036    0.58        0.95      15.12     1.53
8          1            22,890    20.14       124.93    249.60    34.35
8          64           25,630    1.02        1.27      23.97     3.87
16         1            23,094    17.48       152.58    1,688.23  66.50
16         64           26,819    1.07        1.37      23.07     8.40
=========  ===========  ========  ==========  ========  ========  ======

These host-specific numbers are diagnostic rather than universal. Reservation
size 1 remains the correctness-first default; larger reservations trade unused
allocations on process exit for substantially less lock and CPU pressure.
