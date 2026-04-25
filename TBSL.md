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
| 1-15 | 15 hex chars | Time-machine payload | Uppercase hexadecimal encoding of the decimal payload `SSSSSSSSSSUUUUUUMM`, left-padded with zeroes to 15 characters. |
| 16-20 | 5 hex chars | Entropy or sequence | Random suffix by default, or a sequence suffix when sequenced mode is enabled. |

The decimal payload is composed as follows:

| Decimal digits | Length | Field | Description |
|:--|--:|:--|:--|
| 1-10 | 10 digits | Unix seconds | Seconds since the Unix epoch. |
| 11-16 | 6 digits | Microseconds | Microsecond fraction of the current second. |
| 17-18 | 2 digits | Machine ID | Machine identifier from `00` to `99`. |

## Generation

Generation accepts:

- `machineId`: integer from `0` to `99`; default is `0`.
- `sequenced`: boolean; default is `false`.

The generator:

1. Reads the current Unix time with microsecond precision.
2. Builds the decimal time sequence as `seconds + microseconds`.
3. Appends the two-digit machine ID to form `SSSSSSSSSSUUUUUUMM`.
4. Converts that decimal payload to hexadecimal and left-pads it to 15
   characters.
5. Appends a 5-character hexadecimal suffix:
   - random mode: first 5 hex characters from 3 random bytes;
   - sequenced mode: the next sequence value for the
     `(type = "tbsl", machineId, timestamp)` key, encoded as hex and padded to
     5 characters.
6. Returns the 20-character uppercase hexadecimal string.

Sequence providers should keep returned sequence values within the 20-bit suffix
range, `0x00000` through `0xFFFFF`.

## Parsing

To parse a canonical TBSL value:

1. Validate the string against `^[0-9A-F]{20}$`.
2. Decode characters `1-15` from hexadecimal to the decimal payload.
3. Read the first 10 decimal digits as Unix seconds.
4. Read the next 6 decimal digits as microseconds.
5. Read the final 2 decimal digits as the machine ID.

The suffix is intentionally opaque. It is not needed to recover the timestamp or
machine ID.

## Ordering

Canonical TBSL strings sort lexicographically by their time-machine payload first.
This preserves chronological ordering for generated IDs as long as system clocks
move forward.

For IDs generated within the same microsecond and machine ID:

- random mode provides uniqueness through entropy, but not generation order;
- sequenced mode provides deterministic suffix ordering while the sequence value
  remains within the 5-character hexadecimal suffix.

If the clock moves backward, the implementation either waits for the next usable
time sequence or throws, depending on the configured clock-backward policy.

## Encodings

The canonical representation is uppercase hexadecimal. The package can also
convert canonical TBSL values to and from:

- raw 10-byte binary;
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
- Timestamp precision: microseconds.
- Machine ID range: `0` through `99`.
- Suffix size: 5 hex characters, or 20 bits.
- Maximum suffix cardinality per `(timestamp, machineId)` key: 1,048,576 values.
