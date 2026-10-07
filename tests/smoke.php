<?php

declare(strict_types=1);

use Infocyph\UID\Configuration\RandflakeConfig;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\CUID2;
use Infocyph\UID\DeterministicId;
use Infocyph\UID\Enums\RandflakeFormat;
use Infocyph\UID\Enums\SonyflakeFormat;
use Infocyph\UID\KSUID;
use Infocyph\UID\NanoID;
use Infocyph\UID\ObjectID;
use Infocyph\UID\OpaqueId;
use Infocyph\UID\Randflake;
use Infocyph\UID\RandomId;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Snowflake;
use Infocyph\UID\Sonyflake;
use Infocyph\UID\TBSL;
use Infocyph\UID\TypeID;
use Infocyph\UID\ULID;
use Infocyph\UID\UUID;
use Infocyph\UID\XID;

require ($argv[1] ?? dirname(__DIR__)) . '/vendor/autoload.php';

/** @param array<string, array<string|int, true>> $seen */
function uidSmokeId(string $algorithm, string $id, bool $valid, array &$seen): void
{
    if (!$valid || isset($seen[$algorithm][$id])) {
        throw new RuntimeException($algorithm . ' produced an invalid or repeated ID');
    }

    $seen[$algorithm][$id] = true;
}

$directory = sys_get_temp_dir() . '/uid-smoke-' . bin2hex(random_bytes(6));
mkdir($directory, 0700) || throw new RuntimeException('Unable to create smoke state directory');
$provider = new FilesystemSequenceProvider($directory, 'smoke');
$runtime = new GenerationContext(waitTimeoutMicros: 100_000);
$snowflake = new SnowflakeConfig(datacenterId: 1, workerId: 2, sequenceProvider: $provider, runtime: $runtime);
$sonyflakes = [];
$randflakes = [];
$secret = '0123456789abcdef';
foreach (SonyflakeFormat::cases() as $format) {
    $sonyflakes[] = new SonyflakeConfig(machineId: 42, sequenceProvider: $provider, runtime: $runtime, format: $format);
}
foreach (RandflakeFormat::cases() as $format) {
    $randflakes[] = new RandflakeConfig(7, time() - 5, time() + 300, $secret, $provider, $runtime, $format);
}
$tbsl = new TBSLConfig(machineId: 9, sequenceProvider: $provider, runtime: $runtime);
$binaryGenerators = [
    'ULID monotonic' => [ULID::generateMonotonic(...), ULID::class],
    'ULID random' => [ULID::generateRandom(...), ULID::class],
    'ObjectID' => [ObjectID::generate(...), ObjectID::class],
    'KSUID' => [KSUID::generate(...), KSUID::class],
    'XID' => [XID::generate(...), XID::class],
];
$randomGenerators = [
    CUID2::class => CUID2::generate(...),
    NanoID::class => NanoID::generate(...),
    RandomId::class => RandomId::generate(...),
];
$seen = [];

try {
    for ($cycle = 0; $cycle < 100; ++$cycle) {
        foreach ([1, 3, 4, 5, 6, 7, 8] as $version) {
            $id = match ($version) {
                1 => UUID::v1(),
                3 => UUID::v3('00000000-0000-0000-0000-000000000000', 'smoke-' . $cycle),
                4 => UUID::v4(),
                5 => UUID::v5('00000000-0000-0000-0000-000000000000', 'smoke-' . $cycle),
                6 => UUID::v6(),
                7 => UUID::v7(),
                8 => UUID::v8(),
            };
            uidSmokeId('UUID v' . $version, $id, UUID::isValid($id)
                && UUID::parse($id)['version'] === $version
                && UUID::fromBytes(UUID::toBytes($id)) === $id, $seen);
        }
        $id = UUID::guid(false);
        uidSmokeId('GUID', $id, preg_match('/\A\{[0-9a-f-]{36}\}\z/i', $id) === 1
            && UUID::isValid(trim($id, '{}')), $seen);

        foreach ($binaryGenerators as $name => [$generate, $class]) {
            $id = $generate();
            uidSmokeId($name, $id, $class::isValid($id)
                && $class::fromBytes($class::toBytes($id)) === $id, $seen);
        }
        foreach ($randomGenerators as $class => $generate) {
            $id = $generate();
            uidSmokeId($class, $id, $class::isValid($id), $seen);
        }
        $id = TypeID::generate('smoke');
        uidSmokeId('TypeID', $id, TypeID::isValid($id)
            && TypeID::fromUuid('smoke', TypeID::toUuid($id)) === $id, $seen);
        $id = Snowflake::generateWithConfig($snowflake);
        $parsed = Snowflake::parse($id);
        uidSmokeId('Snowflake', $id, Snowflake::isValid($id)
            && $parsed['datacenter_id'] === 1 && $parsed['worker_id'] === 2
            && Snowflake::fromBytes(Snowflake::toBytes($id)) === $id, $seen);

        foreach ($sonyflakes as $config) {
            $id = Sonyflake::generateWithConfig($config);
            uidSmokeId('Sonyflake ' . $config->format->value, $id, Sonyflake::isValid($id)
                && Sonyflake::parse($id, $config->format)['machine_id'] === 42
                && Sonyflake::fromBytes(Sonyflake::toBytes($id)) === $id, $seen);
        }
        foreach ($randflakes as $config) {
            $id = Randflake::generateWithConfig($config);
            uidSmokeId('Randflake ' . $config->format->value, $id, Randflake::isValid($id, $config->format)
                && Randflake::inspect($id, $secret, $config->format)['node_id'] === 7
                && Randflake::fromBytes(Randflake::toBytes($id, $config->format), $config->format) === $id, $seen);
        }
        foreach (['sequenced' => TBSL::generateWithConfig($tbsl), 'random' => TBSL::generateRandom(9)] as $mode => $id) {
            uidSmokeId('TBSL ' . $mode, $id, TBSL::isValid($id)
                && TBSL::parse($id)['machineId'] === 9
                && TBSL::fromBytes(TBSL::toBytes($id)) === $id, $seen);
        }
        $id = OpaqueId::fromInt($cycle, 'smoke');
        uidSmokeId('OpaqueId', $id, OpaqueId::toInt($id, 'smoke') === $cycle, $seen);
        $id = DeterministicId::fromPayload('smoke-' . $cycle, 24, 'smoke');
        uidSmokeId('DeterministicId', $id, RandomId::isValid($id, 24, '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz')
            && DeterministicId::fromPayload('smoke-' . $cycle, 24, 'smoke') === $id, $seen);
    }
    foreach ($seen as $algorithm => $ids) {
        printf("PASS %s: %d IDs\n", $algorithm, count($ids));
    }
    printf("Passed %d generator variants in one 100-cycle smoke pass.\n", count($seen));
} finally {
    foreach (glob($directory . '/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($directory);
}
