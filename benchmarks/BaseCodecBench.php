<?php

declare(strict_types=1);

namespace Infocyph\UID\Benchmarks;

use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\DecimalBytes;
use Infocyph\UID\Support\NumericIdCodec;
use Infocyph\UID\Support\TypeIdCodec;
use PhpBench\Attributes as Bench;

final class BaseCodecBench
{
    /** @var array<int, string> */
    private array $decimal = [];

    /** @var array<int, array<int, string>> */
    private array $encoded = [];

    /** @var array<int, string> */
    private array $samples = [];

    private string $typeIdEncoded;

    public function __construct()
    {
        require_once __DIR__ . '/BenchBootstrap.php';
        BenchBootstrap::load();

        foreach ([8, 10, 12, 16, 20, 32] as $length) {
            $sample = random_bytes($length);
            $this->samples[$length] = $sample;
            $this->decimal[$length] = DecimalBytes::fromBytes($sample);

            foreach ([10, 16, 32, 36, 58, 62] as $base) {
                $this->encoded[$base][$length] = BaseEncoder::encodeBytes($sample, $base);
            }
        }

        $this->typeIdEncoded = TypeIdCodec::encode($this->samples[16]);
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

    #[Bench\Revs(250), Bench\Iterations(5), Bench\ParamProviders('provideBaseLengthPairs')]
    public function benchDecode(array $params): void
    {
        BaseEncoder::decodeToBytes(
            $this->encoded[$params['base']][$params['length']],
            $params['base'],
            $params['length'],
        );
    }

    #[Bench\Revs(500), Bench\Iterations(5), Bench\ParamProviders('provideLengths')]
    public function benchNumericFromBytes(array $params): void
    {
        NumericIdCodec::decimalFromBytes($this->sample($params), $params['length']);
    }

    #[Bench\Revs(500), Bench\Iterations(5), Bench\ParamProviders('provideLengths')]
    public function benchNumericToBytes(array $params): void
    {
        DecimalBytes::toFixedBytes($this->decimal[$params['length']], $params['length']);
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchTypeIdDecode(): void
    {
        TypeIdCodec::decode($this->typeIdEncoded);
    }

    #[Bench\Revs(1000), Bench\Iterations(5)]
    public function benchTypeIdEncode(): void
    {
        TypeIdCodec::encode($this->samples[16]);
    }

    /**
     * @return array<string, array{base:int,length:int}>
     */
    public function provideBaseLengthPairs(): array
    {
        $pairs = [];

        foreach ([10, 16, 32, 36, 58, 62] as $base) {
            foreach ([8, 10, 12, 16, 20, 32] as $length) {
                $pairs['base-' . $base . '-' . $length . '-bytes'] = [
                    'base' => $base,
                    'length' => $length,
                ];
            }
        }

        return $pairs;
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
