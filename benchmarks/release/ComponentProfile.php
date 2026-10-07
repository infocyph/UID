<?php

declare(strict_types=1);

use Infocyph\UID\CUID2;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\DecimalBytes;
use Infocyph\UID\Support\NumericIdCodec;
use Infocyph\UID\Support\TypeIdCodec;

$options = getopt('', ['target-root:', 'release:', 'output:']);
$root = $options['target-root'] ?? null;
$release = $options['release'] ?? null;
$output = $options['output'] ?? null;

if (!is_string($root) || $root === '' || !is_string($release) || $release === '' || !is_string($output) || $output === '') {
    throw new InvalidArgumentException('Usage: php ComponentProfile.php --target-root=DIR --release=NAME --output=FILE');
}

require_once rtrim($root, '/') . '/vendor/autoload.php';

/**
 * @return array{median_ns:float,min_ns:float,max_ns:float}
 */
function uidProfile(callable $operation, int $revolutions = 1_000, int $repetitions = 5): array
{
    for ($warmup = 0; $warmup < min(100, $revolutions); ++$warmup) {
        $operation();
    }

    $samples = [];

    for ($repetition = 0; $repetition < $repetitions; ++$repetition) {
        $started = hrtime(true);

        for ($index = 0; $index < $revolutions; ++$index) {
            $operation();
        }

        $samples[] = (hrtime(true) - $started) / $revolutions;
    }

    sort($samples, SORT_NUMERIC);
    $median = $samples[(int) floor(count($samples) / 2)];

    return [
        'median_ns' => round($median, 3),
        'min_ns' => round(min($samples), 3),
        'max_ns' => round(max($samples), 3),
    ];
}

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
    throw new LogicException('Unable to bind CUID2 profiling helpers');
}

($fingerprint)();

$metrics = [
    'cuid2_generate_warm' => uidProfile(static fn(): string => CUID2::generate(), 2_000),
    'cuid2_generate_cold' => uidProfile(
        static function () use ($resetFingerprint): string {
            $resetFingerprint();

            return CUID2::generate();
        },
        250,
    ),
    'cuid2_fingerprint_warm' => uidProfile($fingerprint, 5_000),
    'cuid2_fingerprint_cold' => uidProfile(
        static function () use ($resetFingerprint, $fingerprint): string {
            $resetFingerprint();

            return $fingerprint();
        },
        250,
    ),
];

$samples = [];
$decimals = [];
$encoded = [];

foreach ([8, 10, 12, 16, 20, 32, 64] as $length) {
    $material = '';
    $counter = 0;

    while (strlen($material) < $length) {
        $material .= hash('sha256', 'uid-profile-' . $length . '-' . $counter, true);
        ++$counter;
    }

    $samples[$length] = substr($material, 0, $length);
    $decimals[$length] = DecimalBytes::fromBytes($samples[$length]);

    foreach ([10, 16, 32, 36, 58, 62] as $base) {
        $encoded[$base][$length] = BaseEncoder::encodeBytes($samples[$length], $base);
        $metrics['base' . $base . '_encode_' . $length] = uidProfile(
            static fn(): string => BaseEncoder::encodeBytes($samples[$length], $base),
            500,
        );
        $metrics['base' . $base . '_decode_' . $length] = uidProfile(
            static fn(): string => BaseEncoder::decodeToBytes(
                $encoded[$base][$length],
                $base,
                $length,
            ),
            500,
        );
    }

    $metrics['numeric_from_bytes_' . $length] = uidProfile(
        static fn(): string => NumericIdCodec::decimalFromBytes($samples[$length], $length),
        500,
    );
    $metrics['numeric_to_bytes_' . $length] = uidProfile(
        static fn(): string => DecimalBytes::toFixedBytes($decimals[$length], $length),
        500,
    );
}

$typeId = TypeIdCodec::encode($samples[16]);
$metrics['typeid_encode_16'] = uidProfile(
    static fn(): string => TypeIdCodec::encode($samples[16]),
    1_000,
);
$metrics['typeid_decode_16'] = uidProfile(
    static fn(): string => TypeIdCodec::decode($typeId),
    1_000,
);

file_put_contents(
    $output,
    json_encode([
        'release' => $release,
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'php_version' => PHP_VERSION,
        'metrics' => $metrics,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
);
