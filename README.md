# UID

![Libraries.io dependency status for GitHub repo](https://img.shields.io/librariesio/github/infocyph/uid)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/uid)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/uid)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/infocyph/uid)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/infocyph/uid)

UUID (RFC 4122 + Unofficial/Draft), ULID, Snowflake ID, Sonyflake ID generator!

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

#### UUID v1: Time-based UUID.

```php
// Get v1 UUID (Time based)
\Infocyph\UID\UUID::v1();

// alternatively can also use
\Infocyph\UID\uuid1();

// Get generated node, for further use
$node = \Infocyph\UID\UUID::getNode(1);

// Pass your pre-generated node (for node specific UUID)
\Infocyph\UID\UUID::v1($node);

// Extract generation/creation time from UUID
\Infocyph\UID\UUID::getTime($uuid); // returns DateTimeInterface object
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

```php
// Get v6 UUID (Time based)
\Infocyph\UID\UUID::v6();

// alternatively can also use
\Infocyph\UID\uuid6();

// Get generated node, for further use
$node = \Infocyph\UID\UUID::getNode(6);

// Pass your pre-generated node (for node specific UUID)
\Infocyph\UID\UUID::v6($node);

// Extract generation/creation time from UUID
\Infocyph\UID\UUID::getTime($uuid); // returns DateTimeInterface object
```

#### UUID v7 (draft-based/unofficial): Time-based UUID.

```php
// Get v7 UUID (Time based)
\Infocyph\UID\UUID::v7();

// alternatively can also use
\Infocyph\UID\uuid7();

// Get generated node, for further use
$node = \Infocyph\UID\UUID::getNode(7);

// Pass your pre-generated node (for node specific UUID)
\Infocyph\UID\UUID::v7($node);

// Extract generation/creation time from UUID
\Infocyph\UID\UUID::getTime($uuid); // returns DateTimeInterface object
```

#### UUID v8 (draft-based/unofficial): Time-based UUID. Lexicographically sortable.

```php
// Get v6 UUID (Time based)
\Infocyph\UID\UUID::v8();

// alternatively can also use
\Infocyph\UID\uuid8();

// Get generated node, for further use
$node = \Infocyph\UID\UUID::getNode(8);

// Pass your pre-generated node (for node specific UUID)
\Infocyph\UID\UUID::v8($node);

// Extract generation/creation time from UUID
\Infocyph\UID\UUID::getTime($uuid); // returns DateTimeInterface object
```

#### Additional

```php
// Validate any UUID
\Infocyph\UID\UUID::isValid($uuid); // true/false

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
\Infocyph\UID\Snowflake::generate();

// Parse Snowflake ID
\Infocyph\UID\Snowflake::parse($snowflake); // returns [time => DateTimeInterface object, sequence, worker_id, datacenter_id]

// Important: by default the start time is set to `2020-01-01 00:00:00`
// You should/can change this accordingly (but once set, this should always stay same as long as your project lives)
// & if this need change, you must call this before any Snowflake call (generate/parse)
\Infocyph\UID\Snowflake::setStartTimeStamp('2000-01-01 00:00:00');
```

### Sonyflake ID

```php
// Get Sonyflake ID
\Infocyph\UID\Sonyflake::generate();

// Parse Snowflake ID
\Infocyph\UID\Sonyflake::parse($snowflake); // returns [time => DateTimeInterface object, sequence, worker_id, datacenter_id]

// Important: by default the start time is set to `2020-01-01 00:00:00`
// You should/can change this accordingly (but once set, this should always stay same as long as your project lives)
// & if this need change, you must call this before any Sonyflake call (generate/parse)
\Infocyph\UID\Sonyflake::setStartTimeStamp('2000-01-01 00:00:00');
```

## Support

Having trouble? Create an issue!

## References

- UUID (RFC4122): https://tools.ietf.org/html/rfc4122
- UUID (Drafts/Proposals): https://datatracker.ietf.org/doc/draft-ietf-uuidrev-rfc4122bis
- ULID: https://github.com/ulid/spec
- Snowflake ID: https://github.com/twitter-archive/snowflake/tree/snowflake-2010
- Sonyflake ID: https://github.com/sony/sonyflake
