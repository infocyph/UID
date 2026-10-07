References
==========

Standards and Specs
-------------------

- UUID RFC 9562: https://datatracker.ietf.org/doc/html/rfc9562
- ULID spec: https://github.com/ulid/spec
- Twitter Snowflake (archived): https://github.com/twitter-archive/snowflake/tree/snowflake-2010
- Sonyflake: https://github.com/sony/sonyflake
- Randflake: https://gosuda.org/randflake
- Randflake source: https://github.com/gosuda/randflake
- NanoID: https://github.com/ai/nanoid
- CUID2: https://github.com/paralleldrive/cuid2

Project-Specific
----------------

- TBSL design note: https://github.com/infocyph/UID/blob/main/TBSL.md
- Randflake PHP example: https://github.com/Adambean/randflake-id-php
- Package source: https://github.com/infocyph/UID
- Packagist: https://packagist.org/packages/infocyph/uid

Pinned Compatibility Revisions
------------------------------

UID 6.0 compatibility vectors and format review are pinned to these upstream
source revisions so later upstream changes cannot silently redefine the release
contract:

- Sonyflake: f167a9d53145b661d05ac28b5702b6f29ea9c502
- Randflake: 6ce19de931101e0e3987a69bea1c729c1bea09c1

The default UID formats remain the legacy stored formats. These revisions apply
only to the explicit upstream-compatible modes.
