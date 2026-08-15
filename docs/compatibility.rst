Compatibility, Uniqueness, and Security
=======================================

Format Compatibility
--------------------

- UUID v1/v3/v4/v5 follows RFC 4122-compatible layouts; UUID v6/v7 follows RFC 9562.
- UUID v8 uses an application-defined payload. Generic parsing does not infer a timestamp.
- TypeID implements the TypeID v0.3 text format over 16 UUID bytes.
- ObjectID implements the BSON 12-byte ObjectID layout and canonical lowercase hexadecimal text.
- ULID uses canonical Crockford Base32 and supports random and process-local monotonic modes.
- Snowflake uses a 41/5/5/12 signed 64-bit layout.
- Sonyflake uses a 39/16/8 signed 64-bit layout with 10 millisecond timestamps.
- Randflake uses an unsigned 64-bit 30/17/17 layout before keyed permutation.
- TBSL is a project-specific 10-byte, uppercase hexadecimal format.
- KSUID and XID retain their standard fixed-length text and binary layouts.

Uniqueness Matrix
-----------------

=====================  ==============================================================
Format                 Uniqueness model
=====================  ==============================================================
UUID v4                Probabilistic cryptographic randomness
UUID v7                Randomness plus process-local monotonic generation
ULID                    Randomness plus optional process-local monotonic generation
TypeID                  Inherits the encoded UUID's uniqueness properties
ObjectID                Timestamp, process-random value, and process-local counter
NanoID / RandomId       Cryptographic randomness
CUID2                   Hashed multi-source entropy
KSUID                    Timestamp plus random payload
XID                      Timestamp, machine data, PID, and counter
Snowflake               Coordinated node plus sequence provider
Sonyflake               Coordinated machine plus sequence provider
Randflake               Coordinated node/sequence plus keyed permutation
TBSL sequenced          Coordinated machine plus sequence provider
=====================  ==============================================================

Probabilistic formats are not mathematically guaranteed unique. The in-memory
sequence provider is process-local and is not safe for cross-process coordination.

Security Matrix
---------------

=====================  ==============================================================
Format                 Security properties
=====================  ==============================================================
RandomId / NanoID      Unpredictable while PHP's CSPRNG remains secure
CUID2                  Opaque, hashed entropy format
UUID v4/v7             Identifiers, not authorization secrets
Snowflake/Sonyflake    Structured values whose metadata may be inferred
TBSL                   Structured value whose timestamp is recoverable
ObjectID/XID/KSUID      Timestamp metadata is recoverable
Randflake               Obscures fields; it is not authenticated encryption
OpaqueId                Reversible obfuscation only
DeterministicId         Stable deterministic token; it does not provide secrecy
=====================  ==============================================================

Always enforce authorization independently of identifier format.

Runtime Requirements
--------------------

- PHP 8.2 or newer on a 64-bit runtime.
- No BCMath dependency.
- PSR-16 is optional and needed only for the PSR simple-cache sequence provider.
