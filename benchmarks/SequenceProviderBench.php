<?php

declare(strict_types=1);

namespace Infocyph\UID\Benchmarks;

use Infocyph\UID\Randflake;
use Infocyph\UID\Snowflake;
use InvalidArgumentException;
use PhpBench\Attributes as Bench;

final class SequenceProviderBench
{
    private int $leaseEnd;

    private int $leaseStart;

    private string $secret;

    public function __construct()
    {
        require_once __DIR__ . '/BenchBootstrap.php';
        BenchBootstrap::load();
        [$this->leaseStart, $this->leaseEnd, $this->secret] = BenchBootstrap::randflakeContext();

        Snowflake::resetSequenceProvider();
        Randflake::resetSequenceProvider();
    }

    #[Bench\Revs(500)]
    #[Bench\Iterations(5)]
    #[Bench\ParamProviders('provideSequenceProviders')]
    public function benchSequenceProvider(array $params): void
    {
        $subject = $params['subject'] ?? null;
        if (!is_string($subject) || $subject === '') {
            throw new InvalidArgumentException('Benchmark subject is required.');
        }

        switch ($subject) {
            case 'snowflake_filesystem':
                Snowflake::useFilesystemSequenceProvider();
                Snowflake::generate(1, 1);

                return;
            case 'snowflake_in_memory':
                Snowflake::useInMemorySequenceProvider();
                Snowflake::generate(1, 1);

                return;
            case 'randflake_filesystem':
                Randflake::useFilesystemSequenceProvider();
                Randflake::generate(1, $this->leaseStart, $this->leaseEnd, $this->secret);

                return;
            case 'randflake_in_memory':
                Randflake::useInMemorySequenceProvider();
                Randflake::generate(1, $this->leaseStart, $this->leaseEnd, $this->secret);

                return;
            default:
                throw new InvalidArgumentException("Unknown sequence provider subject: $subject");
        }
    }

    /**
     * @return array<string, array{subject: string}>
     */
    public function provideSequenceProviders(): array
    {
        return [
            'snowflake_filesystem' => ['subject' => 'snowflake_filesystem'],
            'snowflake_in_memory' => ['subject' => 'snowflake_in_memory'],
            'randflake_filesystem' => ['subject' => 'randflake_filesystem'],
            'randflake_in_memory' => ['subject' => 'randflake_in_memory'],
        ];
    }
}
