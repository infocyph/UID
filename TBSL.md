# TBSL Specification

TBSL is a project-specific, time-based, lexicographically sortable identifier used by
`Infocyph\UID\TBSL`.

This document defines the canonical TBSL text format and the generation rules used
by this package.

## Canonical Format

A canonical TBSL identifier is exactly 20 uppercase hexadecimal characters:

```text
^[0-9A-F]{20}$
```

The canonical string represents 10 bytes, or 80 bits, of data. Lowercase
hexadecimal and non-hexadecimal characters are not canonical TBSL values.

## Field Layout

Character positions are 1-based.

| Characters | Length | Field | Description |
|:--|--:|:--|:--|
| 1-15 | 15 hex chars | Time-machine payload | Uppercase hexadecimal encoding of the 60-bit integer `(unixMicroseconds * 100) + machineId`, left-padded with zeroes to 15 characters. |
| 16-20 | 5 hex chars | Sequence or entropy | Zero-based sequence suffix by default, or random entropy when sequenced mode is disabled. |

The time-machine payload is numeric, not a fixed-width decimal string. Parsing is:

```text
unixMicroseconds = intdiv(payload, 100)
machineId        = payload % 100
```

This representation remains valid when Unix seconds grow beyond ten decimal
digits, as long as the combined value still fits the 60-bit field.

## Generation

Generation accepts:

- `machineId`: integer from `0` to `99`; default is `0`.
- `sequenced`: boolean; default is `true`. Use `generateRandom()` or `sequenced: false` for the entropy suffix.

The generator:

1. Reads the current Unix time with microsecond precision.
2. Converts that time to an integer count of Unix microseconds.
3. Forms the 60-bit time-machine payload as `(unixMicroseconds * 100) + machineId`.
4. Converts that integer to hexadecimal and left-pads it to 15 characters.
5. Appends a 5-character hexadecimal suffix:
   - sequenced mode (default): obtains a positive provider allocation from
     `(type = "tbsl", machineId, timestamp)`, then encodes
     `allocation - 1` as five hexadecimal characters;
   - random mode: first 5 hex characters from 3 random bytes.
6. Returns the 20-character uppercase hexadecimal string.

In sequenced mode, provider allocations `1..0x100000` map to encoded suffixes
`00000..FFFFF`. If that range is exhausted for one timestamp, generation waits
for the next usable timestamp or fails according to the configured bounded-wait
and clock-backward policy.

## Parsing

To parse a canonical TBSL value:

1. Validate the string against `^[0-9A-F]{20}$`.
2. Decode characters `1-15` from hexadecimal to the 60-bit payload.
3. Recover Unix microseconds with `intdiv(payload, 100)`.
4. Recover the machine ID with `payload % 100`.
5. Build the timestamp from the recovered seconds and microsecond fraction.

The suffix is intentionally opaque. It is not needed to recover the timestamp or
machine ID.

## Ordering

Canonical TBSL strings sort lexicographically by their time-machine payload first.
This preserves chronological ordering for generated IDs as long as system clocks
move forward.

For IDs generated within the same microsecond and machine ID:

- random mode provides uniqueness through entropy, but not generation order;
- sequenced mode provides deterministic suffix ordering while the sequence range
  remains available.

If the clock moves backward, the implementation either waits for the next usable
time sequence or throws, depending on the configured clock-backward policy.
Waits are bounded; a frozen injected clock cannot spin indefinitely.

## Encodings

The canonical representation is uppercase hexadecimal. The package can also
convert canonical TBSL values to and from:

- raw 10-byte binary;
- base10;
- base16;
- base32;
- base36;
- base58;
- base62.

Alternate-base encodings are transport encodings only. They do not replace the
canonical 20-character uppercase hexadecimal TBSL string.

## Limits

- Canonical size: 20 hex characters.
- Binary size: 10 bytes.
- Time-machine payload: 60 bits.
- Timestamp precision: microseconds.
- Machine ID range: `0` through `99`.
- Suffix size: 5 hex characters, or 20 bits.
- Sequenced suffix cardinality per `(timestamp, machineId)` key: 1,048,576 values.
