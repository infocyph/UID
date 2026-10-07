<?php

declare(strict_types=1);

namespace Infocyph\UID\Benchmarks;

use Closure;
use Infocyph\UID\Configuration\RandflakeConfig;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\CUID2;
use Infocyph\UID\DeterministicId;
use Infocyph\UID\KSUID;
use Infocyph\UID\NanoID;
use Infocyph\UID\ObjectID;
use Infocyph\UID\OpaqueId;
use Infocyph\UID\Randflake;
use Infocyph\UID\RandomId;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Snowflake;
use Infocyph\UID\Sonyflake;
use Infocyph\UID\TBSL;
use Infocyph\UID\TypeID;
use Infocyph\UID\ULID;
use Infocyph\UID\UUID;
use Infocyph\UID\XID;
use PhpBench\Attributes as Bench;

final class HotspotBench
{
    private Closure $cuidFingerprint;

    private string $opaque;

    private RandflakeConfig $randflakeConfig;

    private Closure $resetCuidFingerprint;

    private SnowflakeConfig $snowflakeConfig;

    private SonyflakeConfig $sonyflakeConfig;

    private TBSLConfig $tbslConfig;

    public function __construct()
    {
        require_once __DIR__ . '/BenchBootstrap.php';
        BenchBootstrap::load();

        $fingerprint = Closure::bind(
            static fn(): string => CUID2::fingerprint(),
            null,
            CUID2::class,
        );
        $resetFingerprint = Closure::bind(
            static function (): void {
                CUID2::$fingerprint = null;
            },
            null,
            CUID2::class,
        );
        if (!$fingerprint instanceof Closure || !$resetFingerprint instanceof Closure) {
            throw new \LogicException('Unable to bind CUID2 benchmark helpers');
        }

        $this->cuidFingerprint = $fingerprint;
        $this->resetCuidFingerprint = $resetFingerprint;
        ($this->cuidFingerprint)();

        $provider = new FilesystemSequenceProvider(namespace: 'phpbench');
        [$leaseStart, $leaseEnd, $secret] = BenchBootstrap::randflakeContext();
        $this->snowflakeConfig = new SnowflakeConfig(sequenceProvider: $provider);
        $this->sonyflakeConfig = new SonyflakeConfig(sequenceProvider: $provider);
        $this->randflakeConfig = new RandflakeConfig(1, $leaseStart, $leaseEnd, $secret, $provider);
        $this->tbslConfig = new TBSLConfig(sequenceProvider: $provider);
        $this->opaque = OpaqueId::fromInt(123456, 'bench');
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchCuid2(): void
    {
        CUID2::generate();
    }

    #[Bench\Revs(100), Bench\Iterations(5)]
    public function benchCuid2ColdGenerate(): void
    {
        ($this->resetCuidFingerprint)();
        CUID2::generate();
    }

    #[Bench\Revs(100), Bench\Iterations(5)]
    public function benchCuid2FingerprintCold(): void
    {
        ($this->resetCuidFingerprint)();
        ($this->cuidFingerprint)();
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchCuid2FingerprintWarm(): void
    {
        ($this->cuidFingerprint)();
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchDeterministicId(): void
    {
        DeterministicId::fromPayload('payload', 24, 'bench');
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchKsuid(): void
    {
        KSUID::generate();
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchNanoId(): void
    {
        NanoID::generate();
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchObjectId(): void
    {
        ObjectID::generate();
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchOpaqueIdDecode(): void
    {
        OpaqueId::toInt($this->opaque, 'bench');
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchOpaqueIdEncode(): void
    {
        OpaqueId::fromInt(123456, 'bench');
    }

    #[Bench\Revs(250), Bench\Iterations(5)]
    public function benchRandflakeFilesystem(): void
    {
        Randflake::generateWithConfig($this->randflakeConfig);
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchRandomId62(): void
    {
        RandomId::generate(21, '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz');
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchRandomId64(): void
    {
        RandomId::generate(21, '_-0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchRandomIdDefault61(): void
    {
        RandomId::generate();
    }

    #[Bench\Revs(250), Bench\Iterations(5)]
    public function benchSnowflakeFilesystem(): void
    {
        Snowflake::generateWithConfig($this->snowflakeConfig);
    }

    #[Bench\Revs(250), Bench\Iterations(5)]
    public function benchSonyflakeFilesystem(): void
    {
        Sonyflake::generateWithConfig($this->sonyflakeConfig);
    }

    #[Bench\Revs(250), Bench\Iterations(5)]
    public function benchTbslFilesystem(): void
    {
        TBSL::generateWithConfig($this->tbslConfig);
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchTypeId(): void
    {
        TypeID::generate('user');
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchUlidMonotonic(): void
    {
        ULID::generateMonotonic();
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchUlidRandom(): void
    {
        ULID::generateRandom();
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchUuidV4(): void
    {
        UUID::v4();
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchUuidV7(): void
    {
        UUID::v7();
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchXid(): void
    {
        XID::generate();
    }
}
