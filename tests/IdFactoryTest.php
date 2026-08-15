<?php

declare(strict_types=1);

use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\Configuration\RandflakeConfig;
use Infocyph\UID\Enums\UlidGenerationMode;
use Infocyph\UID\Id;
use Infocyph\UID\RandomId;
use Infocyph\UID\Value\UuidValue;

test('Id factory basic methods', function () {
    $ksuid = Id::ksuid();
    $xid = Id::xid();
    $typeId = Id::typeId('user');
    $objectId = Id::objectId();
    $uuid1 = Id::uuid1();
    $namespace = Id::uuid4();
    $uuid3 = Id::uuid3($namespace, 'id-factory');
    $uuid4 = Id::uuid4();
    $uuid5 = Id::uuid5($namespace, 'id-factory');
    $uuid6 = Id::uuid6();
    $uuid = Id::uuid7();
    $uuid8 = Id::uuid8();
    $ulid = Id::ulid(null, UlidGenerationMode::RANDOM);
    $snowflake = Id::snowflake();
    $sonyflake = Id::sonyflake();
    $tbsl = Id::tbsl();
    $now = time();
    $randflake = Id::randflake(new RandflakeConfig(
        nodeId: 1,
        leaseStart: $now - 5,
        leaseEnd: $now + 300,
        secret: 'super-secret-key',
    ));

    expect($ksuid)->toBeString()->toHaveLength(27)
        ->and($xid)->toBeString()->toHaveLength(20)
        ->and($typeId)->toStartWith('user_')->toHaveLength(31)
        ->and($objectId)->toHaveLength(24)
        ->and($uuid1)->toBeString()->toHaveLength(36)
        ->and($uuid3)->toBeString()->toHaveLength(36)
        ->and($uuid4)->toBeString()->toHaveLength(36)
        ->and($uuid5)->toBeString()->toHaveLength(36)
        ->and($uuid6)->toBeString()->toHaveLength(36)
        ->and($uuid)->toBeString()->toHaveLength(36)
        ->and($uuid8)->toBeString()->toHaveLength(36)
        ->and($ulid)->toBeString()->toHaveLength(26)
        ->and((string)$snowflake)->toBeString()->not()->toBeEmpty()
        ->and((string)$sonyflake)->toBeString()->not()->toBeEmpty()
        ->and((string)$tbsl)->toBeString()->toHaveLength(20)
        ->and((string)$randflake)->toBeString()->not()->toBeEmpty();
});

test('value objects remain available from their owning type', function () {
    $uuidValue = new UuidValue(Id::uuid7());
    expect($uuidValue)->toBeInstanceOf(UuidValue::class)
        ->and($uuidValue->toString())->toHaveLength(36)
        ->and($uuidValue->getVersion())->toBe(7);
});

test('Id factory random strategy', function () {
    $nano = Id::nanoId(10);
    $cuid2 = Id::cuid2(24);
    $random = Id::random(10);
    $deterministic = Id::deterministic('payload', 16, 'ns');

    expect($nano)->toHaveLength(10)
        ->and($cuid2)->toHaveLength(24)
        ->and(RandomId::isValid($random, 10))->toBeTrue()
        ->and($deterministic)->toHaveLength(16);
});

test('configuration objects keep generation policy separate from representation', function () {
    $snowflake = Id::snowflake(new SnowflakeConfig());
    $sonyflake = Id::sonyflake(new SonyflakeConfig());
    $tbsl = Id::tbsl(new TBSLConfig());
    $now = time();
    $randflake = Id::randflake(new RandflakeConfig(
        nodeId: 1,
        leaseStart: $now - 5,
        leaseEnd: $now + 300,
        secret: 'super-secret-key',
    ));

    expect($snowflake)->toBeString()
        ->and($sonyflake)->toBeString()
        ->and($tbsl)->toHaveLength(20)
        ->and($randflake)->toBeString();
});
