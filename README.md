# UID

[![build](https://github.com/infocyph/UID/actions/workflows/build.yml/badge.svg?branch=main)](https://github.com/infocyph/UID/actions/workflows/build.yml)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/cec4c7ed0e274b3da4571973732a363e)](https://app.codacy.com/gh/infocyph/UID/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
![Libraries.io dependency status for GitHub repo](https://img.shields.io/librariesio/github/infocyph/uid)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/uid)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/uid)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/infocyph/uid)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/infocyph/uid)

An AIO Unique ID generator for php. Supports,
- UUID (RFC 4122 + Unofficial/Draft)
- ULID (ulid specification)
- Snowflake ID (Twitter Snowflake specification)
- Sonyflake ID (Snowflake Inspired specification, ported from Golang)
- TBSL (library exclusive)

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
    * [TBSL ID](#tbsl-time-based-keys-with-lexicographic-sorting-library-exclusive)
* [Benchmark](#benchmark)
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
But, if you wanna track the source of the UUIDs, you should use it (pre-define the node per server & pass it accordingly).

#### UUID v1: Time-based UUID.

- Generate v1 UUID
```php
// Get v1 UUID
\Infocyph\UID\UUID::v1();
// alternatively can also use
\Infocyph\UID\uuid1();
```
- Pass your pre-generated node (for node specific UUID)
```php
\Infocyph\UID\UUID::v1($node); // check additional section for how to generate one
```

#### UUID v3: Namespace based UUID.

- Generate v3 UUID
```php
// Get v3 UUID
\Infocyph\UID\UUID::v3('a pre-generated UUID', 'the string you wanna get UUID for');
// alternatively can also use
\Infocyph\UID\uuid3();
```
- Get v3 UUID for predefined namespaces (RFC4122 #Appendix C)
```php
/**
* You can pass X500, URL, OID, DNS (check RFC4122 #Appendix C)
*/
\Infocyph\UID\UUID::v3('url', 'abmmhasan.github.io');
```
- You can generate a UUID & use as namespace as well
```php
\Infocyph\UID\UUID::v3('fa1700dd-828c-4d1b-8e6d-a6104807da90', 'abmmhasan.github.io');
```

#### UUID v4: Random UUID.

- Generate v4 UUID
```php
// Get v4 UUID (completely random)
\Infocyph\UID\UUID::v4();
// alternatively can also use
\Infocyph\UID\uuid4();
```

#### UUID v5: Namespace based UUID.

- Generate v5 UUID
```php
// Get v5 UUID
\Infocyph\UID\UUID::v5('a pre-generated UUID', 'the string you wanna get UUID for');
// alternatively can also use
\Infocyph\UID\uuid5();
```
- Get v5 UUID for predefined namespaces (RFC4122 #Appendix C)
```php
/**
* You can pass X500, URL, OID, DNS (check RFC4122 #Appendix C)
*/
\Infocyph\UID\UUID::v5('url', 'abmmhasan.github.io');
```
- You can generate a UUID & use as namespace as well
```php
\Infocyph\UID\UUID::v5('fa1700dd-828c-4d1b-8e6d-a6104807da90', 'abmmhasan.github.io');
```

#### UUID v6 (draft-based/unofficial): Time-based UUID.

- Generate v6 UUID
```php
// Get v6 UUID (Time based)
\Infocyph\UID\UUID::v6();
// alternatively can also use
\Infocyph\UID\uuid6();
```
- Or if you wanna get v6 UUID using predefined node:
```php
\Infocyph\UID\UUID::v6($node); // check additional section for how to generate one
```

#### UUID v7 (draft-based/unofficial): Time-based UUID.

- Generate v7 UUID
```php
// Get v7 UUID for current time
\Infocyph\UID\UUID::v7();
// alternatively can also use
\Infocyph\UID\uuid7();
```
- Or if you wanna get v7 UUID using predefined node:
```php
\Infocyph\UID\UUID::v7(null, $node); // check additional section for, how to generate one
```
- Or if you wanna get v7 UUID using predefined time:
```php
$timeInterface = new DateTime(); // DateTime implements DateTimeInterface
\Infocyph\UID\UUID::v7($timeInterface);
```
- You can combine both parameters together.

#### UUID v8 (draft-based/unofficial): Time-based UUID. Lexicographically sortable.

- Generate v8 UUID
```php
// Get v8 UUID
\Infocyph\UID\UUID::v8();
// alternatively can also use
\Infocyph\UID\uuid8();
```
- Or if you wanna get v8 UUID using predefined node:
```php
\Infocyph\UID\UUID::v8($node); // check additional section for, how to generate one
```

#### Additional

- Generate node for further use (with version: 1, 6, 7, 8)
```php
\Infocyph\UID\UUID::getNode();
```
- Parse any UUID string:
```php
\Infocyph\UID\UUID::parse($uuid); // returns ['isValid', 'version', 'time', 'node']
```

### ULID (Universally Unique Lexicographically Sortable Identifier)

- Generating ULID is very simple,
```php
\Infocyph\UID\ULID::generate();
```
- Or if you wanna get ULID for specific time:
```php
\Infocyph\UID\ULID::generate(new DateTimeImmutable('2020-01-01 00:00:00'));
```
- Extract datetime from any ULID string:
```php
\Infocyph\UID\ULID::getTime($ulid); // returns DateTimeInterface object
```
- Validate any ULID string:
```php
\Infocyph\UID\ULID::isValid($ulid); // true/false
```

### Snowflake ID

- Generate a new Snowflake ID (You can also pass your pre-generated worker_id & datacenter_id for server/module detection):
```php
// Get Snowflake ID
// optionally you can set worker_id & datacenter_id, for server/module detection
\Infocyph\UID\Snowflake::generate();
// alternatively
\Infocyph\UID\snowflake();
```
- Parse Snowflake ID (get the timestamp, sequence, worker_id, datacenter_id from any Snowflake ID):
```php
// Parse Snowflake ID
// returns [time => DateTimeInterface object, sequence, worker_id, datacenter_id]
\Infocyph\UID\Snowflake::parse($snowflake);
```
- Specify start time for Snowflake ID (a Snowflake ID is unique upto 69 years from the start date):
```php
// By default, the start time is set to `2020-01-01 00:00:00`, which is changeable
// but if changed, this should always stay same as long as your project lives
// & must call this before any Snowflake call (generate/parse)
\Infocyph\UID\Snowflake::setStartTimeStamp('2000-01-01 00:00:00');
```

### Sonyflake ID

- Generate a new Sonyflake ID (You can also pass your pre-generated machine_id for server detection):
```php
// Get Sonyflake ID
// optionally set machine_id, for server detection
\Infocyph\UID\Sonyflake::generate();
// alternatively
\Infocyph\UID\sonyflake();
```
- Parse Sonyflake ID (get the timestamp, sequence, machine_id from any Snowflake ID):
```php
// Parse Sonyflake ID
// returns [time => DateTimeInterface object, sequence, machine_id]
\Infocyph\UID\Sonyflake::parse($sonyflake);
```
- Specify start time for Sonyflake ID (a Sonyflake ID is unique upto 174 years from the start date):
```php
// By default, the start time is set to `2020-01-01 00:00:00`, which is changeable
// but if changed, this should always stay same as long as your project lives
// & must call this before any Sonyflake call (generate/parse)
\Infocyph\UID\Sonyflake::setStartTimeStamp('2000-01-01 00:00:00');
```

### TBSL: Time-Based Keys with Lexicographic Sorting (library exclusive)

- Get TBSL ID (You can also pass your pre-generated machine_id for server detection):

```php
// Get TBSL ID
// optionally set machine_id, for server detection
\Infocyph\UID\TBSL::generate();
// alternatively
\Infocyph\UID\tbsl();
```
- Parse TBSL ID (get the timestamp, machine_id):
```php
// Parse TBSL
// returns [isValid, time => DateTimeInterface object, machine_id]
\Infocyph\UID\TBSL::parse($tbsl);
```

## Benchmark

| Type                       |                               Generation time (ms)                                |
|:---------------------------|:---------------------------------------------------------------------------------:|
| UUID v1 (random node)      |                          0.00411 (ramsey/Uuid: 0.18753)                           |
| UUID v1 (fixed node)       |                          0.00115 (ramsey/Uuid: 0.17386)                           |         
| UUID v3 (custom namespace) |                          0.00257 (ramsey/Uuid: 0.03015)                           |         
| UUID v4                    |                          0.00362 (ramsey/Uuid: 0.16501)                           |         
| UUID v5 (custom namespace) |                          0.00108 (ramsey/Uuid: 0.03658)                           |       
| UUID v6 (random node)      |                          0.00444 (ramsey/Uuid: 0.17469)                           |     
| UUID v6 (fixed node)       |                          0.00164 (ramsey/Uuid: 0.17382)                           |     
| UUID v7 (random node)      |                          0.00503 (ramsey/Uuid: 0.16278)                           |    
| UUID v7 (fixed node)**     |                          0.00154 (ramsey/Uuid: 0.18753)                           |   
| UUID v8 (random node)      |                            0.00505 (ramsey/Uuid: N/A)                             |  
| UUID v8 (fixed node)       | 0.00209 (ramsey/Uuid: 0.16029 _*predefined random node, not usable as signature_) |              
| ULID                       |                    0.00506 (robinvdvleuten/php-ulid: 0.00508)                     |             
| TBSL                       |                              0.0034 (library unique)                              |            
| Snowflake                  |                     0.13951 (godruoyi/php-snowflake: 0.14856)                     |            
| Sonyflake                  |                     0.13821 (godruoyi/php-snowflake: 0.14583)                     |

## Support

Having trouble? Create an issue!

## References

- UUID (RFC4122): https://tools.ietf.org/html/rfc4122
- UUID (Drafts/Proposals): https://datatracker.ietf.org/doc/draft-ietf-uuidrev-rfc4122bis
- ULID: https://github.com/ulid/spec
- Snowflake ID: https://github.com/twitter-archive/snowflake/tree/snowflake-2010
- Sonyflake ID: https://github.com/sony/sonyflake
