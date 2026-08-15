<?php

declare(strict_types=1);

namespace Infocyph\UID\Benchmarks;

use Infocyph\UID\Configuration\RandflakeConfig;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Randflake;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Sequence\InMemorySequenceProvider;
use Infocyph\UID\Snowflake;
use PhpBench\Attributes as Bench;

final class SequenceProviderBench
{
    private RandflakeConfig $randflakeFilesystem;

    private RandflakeConfig $randflakeInMemory;

    private SnowflakeConfig $snowflakeFilesystem;

    private SnowflakeConfig $snowflakeInMemory;

    public function __construct()
    {
        require_once __DIR__ . '/BenchBootstrap.php';
        BenchBootstrap::load();
        [$leaseStart, $leaseEnd, $secret] = BenchBootstrap::randflakeContext();

        $filesystem = new FilesystemSequenceProvider(namespace: 'phpbench-sequence');
        $memory = new InMemorySequenceProvider();
        $this->snowflakeFilesystem = new SnowflakeConfig(1, 1, sequenceProvider: $filesystem);
        $this->snowflakeInMemory = new SnowflakeConfig(1, 1, sequenceProvider: $memory);
        $this->randflakeFilesystem = new RandflakeConfig(1, $leaseStart, $leaseEnd, $secret, $filesystem);
        $this->randflakeInMemory = new RandflakeConfig(1, $leaseStart, $leaseEnd, $secret, $memory);
    }

    #[Bench\Revs(500), Bench\Iterations(5)]
    public function benchRandflakeFilesystem(): void
    {
        Randflake::generateWithConfig($this->randflakeFilesystem);
    }

    #[Bench\Revs(500), Bench\Iterations(5)]
    public function benchRandflakeInMemory(): void
    {
        Randflake::generateWithConfig($this->randflakeInMemory);
    }

    #[Bench\Revs(500), Bench\Iterations(5)]
    public function benchSnowflakeFilesystem(): void
    {
        Snowflake::generateWithConfig($this->snowflakeFilesystem);
    }

    #[Bench\Revs(500), Bench\Iterations(5)]
    public function benchSnowflakeInMemory(): void
    {
        Snowflake::generateWithConfig($this->snowflakeInMemory);
    }
}
