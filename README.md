# UID

[![build](https://github.com/infocyph/UID/actions/workflows/build.yml/badge.svg?branch=main)](https://github.com/infocyph/UID/actions/workflows/build.yml)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/cec4c7ed0e274b3da4571973732a363e)](https://app.codacy.com/gh/infocyph/UID/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
![Libraries.io dependency status for GitHub repo](https://img.shields.io/librariesio/github/infocyph/uid)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/uid)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/uid)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/infocyph/uid)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/infocyph/uid)

UUID (RFC 4122 + Unofficial/Draft), ULID, Snowflake ID, Sonyflake ID, TBSL (library exclusive) generator!

## Table of contents

<!--ts-->

* [Prerequisites](#prerequisites)
* [Installation](#installation)
* [Usage](#usage)
    * [UUID](#uuid-universal-unique-identifier)
        * [UUID v1](#uuid-v1-time-based-uuid)
        * [UUID v3](#uuid-v3-namespace-based-uuid)
        * [UUID v4](#uuid-v4-random-uuid)
        * [UUID v5](#uuid-v5-namespace-based-uuid)
        * [UUID v6](#uuid-v6-draft-basedunofficial-time-based-uuid)
        * [UUID v7](#uuid-v7-draft-basedunofficial-time-based-uuid)
        * [UUID v8](#uuid-v8-draft-basedunofficial-time-based-uuid-lexicographically-sortable)
        * [Additional](#additional)
    * [ULID](#ulid-universally-unique-lexicographically-sortable-identifier)
    * [Snowflake ID](#snowflake-id)
    * [Sonyflake ID](#sonyflake-id)
    * [TBSL ID](#tbsl-time-based-keys-with-lexicographic-sorting)
* [Support](#support)
* [References](#references)

<!--te-->

## Prerequisites

Language: PHP 8/+

## Installation

```
composer require infocyph/uid
```

## Usage

### UUID (Universal Unique Identifier)

The node specific UUID's `$node` parameter (1, 6, 7, 8) is optional. If not provided, it will be generated randomly.
But,
if you wanna track the source of the UUIDs, you should use it (pre-define the node per server & pass it accordingly).

#### UUID v1: Time-based UUID.

```php
// Get v1 UUID (Time based)
\Infocyph\UID\UUID::v1();

// alternatively can also use
\Infocyph\UID\uuid1();

// Pass your pre-generated node (for node specific UUID)
\Infocyph\UID\UUID::v1($node); // check additional section for how to generate one
```

#### UUID v3: Namespace based UUID.

```php
// Get v3 UUID for 'TestString'
\Infocyph\UID\UUID::v3('TestString');

// alternatively can also use
\Infocyph\UID\uuid3();

/**
* Get v3 UUID for an URL & pre-defined namespace
* You can pass X500, URL, OID, DNS (check RFC4122 #Appendix C)
*/
\Infocyph\UID\UUID::v3('abmmhasan.github.io','url');

// You can generate a random UUID & use as namespace as well
\Infocyph\UID\UUID::v3('abmmhasan.github.io','fa1700dd-828c-4d1b-8e6d-a6104807da90');
```

#### UUID v4: Random UUID.

```php
// Get v4 UUID (completely random)
\Infocyph\UID\UUID::v4();

// alternatively can also use
\Infocyph\UID\uuid4();
```

#### UUID v5: Namespace based UUID.

Better replacement for v3 due to better hashing algorithm (SHA1 instead of MD5).

```php
// Get v5 UUID for 'TestString'
\Infocyph\UID\UUID::v5('TestString');

// alternatively can also use
\Infocyph\UID\uuid5();

/**
* Get v5 UUID for an URL & pre-defined namespace
* You can pass X500, URL, OID, DNS (check RFC4122 #Appendix C)
*/
\Infocyph\UID\UUID::v5('abmmhasan.github.io','url');

// You can generate a random UUID & use as namespace as well
\Infocyph\UID\UUID::v5('abmmhasan.github.io','fa1700dd-828c-4d1b-8e6d-a6104807da90');
```

#### UUID v6 (draft-based/unofficial): Time-based UUID.

Better replacement for v1. Provides more randomness & uniqueness.

```php
// Get v6 UUID (Time based)
\Infocyph\UID\UUID::v6();

// alternatively can also use
\Infocyph\UID\uuid6();

// Pass your pre-generated node (for node specific UUID)
\Infocyph\UID\UUID::v6($node); // check additional section for how to generate one
```

#### UUID v7 (draft-based/unofficial): Time-based UUID.

```php
// Get v7 UUID (Time based)
\Infocyph\UID\UUID::v7();

// alternatively can also use
\Infocyph\UID\uuid7();

// Pass your pre-generated node (for node specific UUID)
\Infocyph\UID\UUID::v7($node); // check additional section for how to generate one
```

#### UUID v8 (draft-based/unofficial): Time-based UUID. Lexicographically sortable.

```php
// Get v6 UUID (Time based)
\Infocyph\UID\UUID::v8();

// alternatively can also use
\Infocyph\UID\uuid8();

// Pass your pre-generated node (for node specific UUID)
\Infocyph\UID\UUID::v8($node); // check additional section for how to generate one
```

#### Additional

```php
// Generate node for further use (with version: 1, 6, 7, 8)
\Infocyph\UID\UUID::getNode();

// Parse any UUID string
\Infocyph\UID\UUID::parse($uuid); // returns ['isValid', 'version', 'time', 'node']
```

### ULID (Universally Unique Lexicographically Sortable Identifier)

```php
// Get ULID
\Infocyph\UID\ULID::generate();

// Get ULID time
\Infocyph\UID\ULID::getTime($ulid); // returns DateTimeInterface object

// Validate ULID
\Infocyph\UID\ULID::isValid($ulid); // true/false
```

### Snowflake ID

```php
// Get Snowflake ID
// optionally you can set worker_id & datacenter_id, for server/module detection
\Infocyph\UID\Snowflake::generate();
// alternatively
\Infocyph\UID\snowflake();

// Parse Snowflake ID
// returns [time => DateTimeInterface object, sequence, worker_id, datacenter_id]
\Infocyph\UID\Snowflake::parse($snowflake);

// By default, the start time is set to `2020-01-01 00:00:00`, which is changeable
// but if changed, this should always stay same as long as your project lives
// & must call this before any Snowflake call (generate/parse)
\Infocyph\UID\Snowflake::setStartTimeStamp('2000-01-01 00:00:00');
```

### Sonyflake ID

```php
// Get Sonyflake ID
// optionally set machine_id, for server detection
\Infocyph\UID\Sonyflake::generate();
// alternatively
\Infocyph\UID\sonyflake();

// Parse Sonyflake ID
// returns [time => DateTimeInterface object, sequence, machine_id]
\Infocyph\UID\Sonyflake::parse($sonyflake);

// By default, the start time is set to `2020-01-01 00:00:00`, which is changeable
// but if changed, this should always stay same as long as your project lives
// & must call this before any Sonyflake call (generate/parse)
\Infocyph\UID\Sonyflake::setStartTimeStamp('2000-01-01 00:00:00');
```

### TBSL: Time-Based Keys with Lexicographic Sorting

Library exclusive.

```php
// Get TBSL ID
// optionally set machine_id, for server detection
\Infocyph\UID\TBSL::generate();
// alternatively
\Infocyph\UID\tbsl();

// Parse TBSL
// returns [isValid, time => DateTimeInterface object, machine_id]
\Infocyph\UID\TBSL::parse($tbsl);
```

## Benchmark

| Type                       | Total Duration (1M Generation, Single thread) |
|:---------------------------|:---------------------------------------------:|
| UUID v1 (random node)      |                   	1.23968s                   |
| UUID v1 (fixed node)	      |                   0.94521s                    |         
| UUID v3 (custom namespace) |                   	0.76439s                   |         
| UUID v4	                   |                   0.70501s                    |         
| UUID v5 (custom namespace) |                   	0.89831s                   |       
| UUID v6 (random node)	     |                   1.39867s                    |     
| UUID v6 (fixed node)	      |                   1.42344s                    |     
| UUID v7 (random node)      |                   	1.40466s                   |    
| UUID v7 (fixed node)       |                   	1.49268s                   |   
| UUID v8 (random node)      |                   	1.80438s                   |  
| UUID v8 (fixed node)       |                   	1.78257s                   |              
| ULID                       |                   	1.79775s                   |             
| TBSL	                      |                   0.80612s                    |            

_Note: Snowflake & Sonyflake not included, due to their way of work_

## Support

Having trouble? Create an issue!

## References

- UUID (RFC4122): https://tools.ietf.org/html/rfc4122
- UUID (Drafts/Proposals): https://datatracker.ietf.org/doc/draft-ietf-uuidrev-rfc4122bis
- ULID: https://github.com/ulid/spec
- Snowflake ID: https://github.com/twitter-archive/snowflake/tree/snowflake-2010
- Sonyflake ID: https://github.com/sony/sonyflake
