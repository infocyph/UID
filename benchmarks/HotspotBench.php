<?php

declare(strict_types=1);

namespace Infocyph\UID\Benchmarks;

use DateTimeImmutable;
use Infocyph\UID\Snowflake;
use Infocyph\UID\ULID;
use Infocyph\UID\UUID;
use PhpBench\Attributes as Bench;

final class HotspotBench
{
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    public function benchSnowflakeParse(): void
    {
        $id = Snowflake::generate();
        Snowflake::parse($id);
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    public function benchUlidMonotonicBurstSameMs(): void
    {
        $fixed = DateTimeImmutable::createFromFormat('U.u', '1700000000.123000');
        ULID::generate($fixed);
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    public function benchUuid7Generation(): void
    {
        UUID::v7();
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    public function benchUuidParse(): void
    {
        $id = UUID::v7();
        UUID::parse($id);
    }
}
