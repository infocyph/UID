# UID

![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/infocyph/uid/ci.yml?branch=main)
![Libraries.io dependency status for GitHub repo](https://img.shields.io/librariesio/github/infocyph/uid)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/uid)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/uid)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/infocyph/uid)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/infocyph/uid)

UUID (RFC 4122 + Unofficial), ULID generator

## Prerequisites

Language: PHP 8/+

## Installation

```
composer require infocyph/uid
```

## Usage

### UUID v1

Time-based UUID.

```php
// Get v1 UUID (Time based)
\Infocyph\UID\UUID::v1();

// Get generated node, for further use
$node = \Infocyph\UID\UUID::getNode(1);

// Pass your pre-generated node (for node specific UUID)
\Infocyph\UID\UUID::v1($node);

// alternatively can also use
\Infocyph\UID\uuid1();

// Extract generation/creation time from UUID
\Infocyph\UID\UUID::getTime($uuid); // returns DateTimeInterface object
```

### UUID v3

Name-based UUID.

```php
// Get v3 UUID for 'TestString'
\Infocyph\UID\UUID::v3('TestString');

/**
* Get v3 UUID for an URL & pre-defined namespace
* You can pass X500, URL, OID, DNS (check RFC4122 #Appendix C)
*/
\Infocyph\UID\UUID::v3('abmmhasan.github.io','url');

// You can generate a random UUID & use as namespace as well
\Infocyph\UID\UUID::v3('abmmhasan.github.io','fa1700dd-828c-4d1b-8e6d-a6104807da90');

// alternatively can also use
\Infocyph\UID\uuid3();
```

### UUID v4

Random UUID.

```php
// Get v4 UUID (completely random)
\Infocyph\UID\UUID::v4();

// alternatively can also use
\Infocyph\UID\uuid4();
```

### UUID v5

Namespace based UUID. Better replacement for v3.

```php
// Get v5 UUID for 'TestString'
\Infocyph\UID\UUID::v5('TestString');

/**
* Get v5 UUID for an URL & pre-defined namespace
* You can pass X500, URL, OID, DNS (check RFC4122 #Appendix C)
*/
\Infocyph\UID\UUID::v5('abmmhasan.github.io','url');

// You can generate a random UUID & use as namespace as well
\Infocyph\UID\UUID::v5('abmmhasan.github.io','fa1700dd-828c-4d1b-8e6d-a6104807da90');

// alternatively can also use
\Infocyph\UID\uuid5();
```

### UUID v6 (draft-based/unofficial)

Time-based UUID. A better replacement for v1.

```php
// Get v6 UUID (Time based)
\Infocyph\UID\UUID::v6();

// Get generated node, for further use
$node = \Infocyph\UID\UUID::getNode(6);

// Pass your pre-generated node (for node specific UUID)
\Infocyph\UID\UUID::v6($node);

// alternatively can also use
\Infocyph\UID\uuid6();

// Extract generation/creation time from UUID
\Infocyph\UID\UUID::getTime($uuid); // returns DateTimeInterface object
```

### UUID v7 (draft-based/unofficial)

Namespace based UUID. Better replacement for v5.

```php
// Get v5 UUID for 'TestString'
\Infocyph\UID\UUID::v7('TestString');

// You can generate a random UUID & use as namespace
\Infocyph\UID\UUID::v7('some random string','fa1700dd-828c-4d1b-8e6d-a6104807da90');

// alternatively can also use
\Infocyph\UID\uuid7();
```

### UUID v8 (draft-based/unofficial)

Time-based UUID. Lexicographically sortable.

```php
// Get v6 UUID (Time based)
\Infocyph\UID\UUID::v8();

// Get generated node, for further use
$node = \Infocyph\UID\UUID::getNode(8);

// Pass your pre-generated node (for node specific UUID)
\Infocyph\UID\UUID::v8($node);

// alternatively can also use
\Infocyph\UID\uuid8();

// Extract generation/creation time from UUID
\Infocyph\UID\UUID::getTime($uuid); // returns DateTimeInterface object
```

## Support

Having trouble? Create an issue!
