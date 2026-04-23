<?php

use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Enums\UlidGenerationMode;
use Infocyph\UID\Id;
use Infocyph\UID\Value\UuidValue;

test('Id factory basic methods', function () {
    $ksuid = Id::ksuid();
    $xid = Id::xid();
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

    expect($ksuid)->toBeString()->toHaveLength(27)
        ->and($xid)->toBeString()->toHaveLength(20)
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
        ->and((string)$tbsl)->toBeString()->toHaveLength(20);
});

test('Id factory value objects', function () {
    $uuidValue = Id::uuid7Value();
    expect($uuidValue)->toBeInstanceOf(UuidValue::class)
        ->and($uuidValue->toString())->toHaveLength(36)
        ->and($uuidValue->getVersion())->toBe(7);
});

test('Id factory random strategy', function () {
    $nano = Id::nanoId(10);
    $cuid2 = Id::cuid2(24);
    $opaque = Id::opaque(10);
    $deterministic = Id::deterministic('payload', 16, 'ns');

    expect($nano)->toHaveLength(10)
        ->and($cuid2)->toHaveLength(24)
        ->and(Id::nanoIdIsValid($nano, 10))->toBeTrue()
        ->and(Id::cuid2IsValid($cuid2))->toBeTrue()
        ->and($opaque)->toHaveLength(10)
        ->and($deterministic)->toHaveLength(16);
});

test('configuration objects apply output modes', function () {
    $snowflake = Id::snowflake(new SnowflakeConfig(outputType: IdOutputType::INT));
    $sonyflake = Id::sonyflake(new SonyflakeConfig(outputType: IdOutputType::INT));
    $tbsl = Id::tbsl(new TBSLConfig(outputType: IdOutputType::BINARY));

    expect($snowflake)->toBeInt()
        ->and($sonyflake)->toBeInt()
        ->and($tbsl)->toBeString()
        ->and(strlen($tbsl))->toBe(10);
});
