<?php

declare(strict_types=1);

namespace Infocyph\UID\Benchmarks;

use Infocyph\UID\Support\BaseEncoder;
use PhpBench\Attributes as Bench;

final class BaseCodecBench
{
    /** @var array<int, string> */
    private array $samples = [];

    public function __construct()
    {
        require_once __DIR__ . '/BenchBootstrap.php';
        BenchBootstrap::load();
        foreach ([8, 10, 12, 16, 20, 32] as $length) {
            $this->samples[$length] = random_bytes($length);
        }
    }

    #[Bench\Revs(1000), Bench\Iterations(5), Bench\ParamProviders('provideLengths')]
    public function benchBase16(array $params): void
    {
        BaseEncoder::encodeBytes($this->sample($params), 16);
    }

    #[Bench\Revs(1000), Bench\Iterations(5), Bench\ParamProviders('provideLengths')]
    public function benchBase32(array $params): void
    {
        BaseEncoder::encodeBytes($this->sample($params), 32);
    }

    #[Bench\Revs(1000), Bench\Iterations(5), Bench\ParamProviders('provideLengths')]
    public function benchBase36(array $params): void
    {
        BaseEncoder::encodeBytes($this->sample($params), 36);
    }

    #[Bench\Revs(1000), Bench\Iterations(5), Bench\ParamProviders('provideLengths')]
    public function benchBase58(array $params): void
    {
        BaseEncoder::encodeBytes($this->sample($params), 58);
    }

    #[Bench\Revs(1000), Bench\Iterations(5), Bench\ParamProviders('provideLengths')]
    public function benchBase62(array $params): void
    {
        BaseEncoder::encodeBytes($this->sample($params), 62);
    }

    #[Bench\Revs(1000), Bench\Iterations(5), Bench\ParamProviders('provideLengths')]
    public function benchDecimal(array $params): void
    {
        BaseEncoder::encodeBytes($this->sample($params), 10);
    }

    /**
     * @return array<string, array{length:int}>
     */
    public function provideLengths(): array
    {
        return [
            '8-bytes' => ['length' => 8],
            '10-bytes' => ['length' => 10],
            '12-bytes' => ['length' => 12],
            '16-bytes' => ['length' => 16],
            '20-bytes' => ['length' => 20],
            '32-bytes' => ['length' => 32],
        ];
    }

    /**
     * @param array{length:int} $params
     */
    private function sample(array $params): string
    {
        return $this->samples[$params['length']];
    }
}
