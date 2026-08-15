# UID

[![Security & Standards](https://github.com/infocyph/UID/actions/workflows/security-standards.yml/badge.svg)](https://github.com/infocyph/UID/actions/workflows/security-standards.yml)
[![Documentation](https://img.shields.io/badge/Documentation-UID-blue?logo=readthedocs&logoColor=white)](https://docs.infocyph.com/projects/UID/)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/UID?color=green&link=https%3A%2F%2Fpackagist.org%2Fpackages%2Finfocyph%2FUID)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/UID)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/infocyph/UID/php)
![GitHub Code Size](https://img.shields.io/github/languages/code-size/infocyph/UID)

All-in-one unique ID toolkit for PHP.

## Features

- UUID (`v1`, `v3`, `v4`, `v5`, `v6`, `v7`, `v8`)
- ULID (monotonic and random modes)
- Snowflake, Sonyflake, Randflake, TBSL
- TypeID, ObjectID, NanoID, RandomId, CUID2, KSUID, XID
- Opaque and deterministic IDs
- Value objects and comparator utilities
- Binary conversion and base encoders (`16`, `32`, `36`, `58`, `62`)
- Pluggable sequence providers (filesystem, memory, PSR-16 cache, callback)

## Requirements

- PHP `>=8.2`
- A 64-bit PHP runtime

## Installation

```bash
composer require infocyph/uid
```

Namespaced generator functions are autoloaded via `src/functions.php`.

## Quick Usage

```php
<?php

use Infocyph\UID\Id;
use Infocyph\UID\CUID2;
use Infocyph\UID\NanoID;

$uuid = Id::uuid();      // default UUID strategy (v7)
$ulid = Id::ulid();
$snowflake = Id::snowflake();
$sonyflake = Id::sonyflake();
$tbsl = Id::tbsl();
$randflake = \Infocyph\UID\Randflake::generate(
    nodeId: 42,
    leaseStart: time() - 5,
    leaseEnd: time() + 300,
    secret: 'super-secret-key',
);
$nanoid = NanoID::generate(21);
$cuid2 = CUID2::generate(24);
```

```php
<?php

use Infocyph\UID\UUID;

$uuid = UUID::v7();
$ok = UUID::isValid($uuid);
$parsed = UUID::parse($uuid);

$bytes = UUID::toBytes($uuid);
$roundTrip = UUID::fromBytes($bytes);

$base58 = UUID::toBase($uuid, 58);
$decoded = UUID::fromBase($base58, 58);
```

The shared byte-level encoder is available as
`Infocyph\UID\Support\BaseEncoder` for bases `16`, `32`, `36`, `58`, and `62`.

## References

- UUID: https://datatracker.ietf.org/doc/html/rfc9562
- ULID: https://github.com/ulid/spec
- Snowflake: https://github.com/twitter-archive/snowflake/tree/snowflake-2010
- Sonyflake: https://github.com/sony/sonyflake
- Randflake: https://github.com/gosuda/randflake
- NanoID: https://github.com/ai/nanoid
- CUID2: https://github.com/paralleldrive/cuid2
- TBSL note: https://github.com/infocyph/UID/blob/main/TBSL.md

## Security

Do not disclose suspected vulnerabilities in a public issue, discussion or pull request. Review the
[security policy](SECURITY.md), then use [GitHub private vulnerability reporting](https://github.com/infocyph/UID/security/advisories/new)
to contact the maintainers confidentially.

UID is protected by [PHPForge](https://github.com/infocyph/PHPForge), an automated quality and security gate covering
tests, static and taint analysis, dependency auditing, architecture checks, and release readiness. Automated controls reduce
risk but do not replace responsible disclosure or manual review.

---

<div align="center">
  <sub><strong>Made with ❤️ for the PHP community</strong></sub><br />
  <sub><a href="LICENSE">MIT Licensed</a></sub><br />
  <a href="https://docs.infocyph.com/projects/UID/en/latest/">Documentation</a> •
  <a href="SECURITY.md">Security</a> •
  <a href="CODE_OF_CONDUCT.md">Code of Conduct</a> •
  <a href="CONTRIBUTING.md">Contributing</a><br />
  <span title="Issue templates" aria-label="Issue templates">🗂️</span>
  <a href="https://github.com/infocyph/UID/issues/new?template=bug_report.yml">Bug</a> •
  <a href="https://github.com/infocyph/UID/issues/new?template=feature_request.yml">Feature</a> •
  <a href="https://github.com/infocyph/UID/issues/new?template=docs_improvement.yml">Documentation</a> •
  <a href="https://github.com/infocyph/UID/issues/new?template=question.yml">Question</a> •
  <a href="https://github.com/infocyph/UID/issues/new?template=ci_failure.yml">CI failure</a><br />
  <span title="Pull request templates" aria-label="Pull request templates">🔀</span>
  <a href="https://github.com/infocyph/UID/compare/main...HEAD?quick_pull=1&amp;template=PULL_REQUEST_TEMPLATE.md">General</a> •
  <a href="https://github.com/infocyph/UID/compare/main...HEAD?quick_pull=1&amp;template=bug_fix.md">Bug fix</a> •
  <a href="https://github.com/infocyph/UID/compare/main...HEAD?quick_pull=1&amp;template=feature.md">Feature</a> •
  <a href="https://github.com/infocyph/UID/compare/main...HEAD?quick_pull=1&amp;template=refactor.md">Refactor</a> •
  <a href="https://github.com/infocyph/UID/compare/main...HEAD?quick_pull=1&amp;template=performance.md">Performance</a> •
  <a href="https://github.com/infocyph/UID/compare/main...HEAD?quick_pull=1&amp;template=security_reliability.md">Security &amp; reliability</a> •
  <a href="https://github.com/infocyph/UID/compare/main...HEAD?quick_pull=1&amp;template=documentation.md">Documentation</a> •
  <a href="https://github.com/infocyph/UID/compare/main...HEAD?quick_pull=1&amp;template=maintenance.md">Maintenance</a>
</div>
