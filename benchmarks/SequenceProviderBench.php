<?php

declare(strict_types=1);

namespace Infocyph\UID\Benchmarks;

use Infocyph\UID\Snowflake;
use PhpBench\Attributes as Bench;

final class SequenceProviderBench
{
    public function __construct()
    {
        Snowflake::resetSequenceProvider();
    }

    #[Bench\Revs(500)]
    #[Bench\Iterations(5)]
    public function benchFilesystemProvider(): void
    {
        Snowflake::useFilesystemSequenceProvider();
        Snowflake::generate(1, 1);
    }

    #[Bench\Revs(500)]
    #[Bench\Iterations(5)]
    public function benchInMemoryProvider(): void
    {
        Snowflake::useInMemorySequenceProvider();
        Snowflake::generate(1, 1);
    }
}
