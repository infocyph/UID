<?php

declare(strict_types=1);

$options = getopt('', ['base-url:', 'release:', 'output:']);
$baseUrl = $options['base-url'] ?? null;
$release = $options['release'] ?? null;
$output = $options['output'] ?? null;

if (!is_string($baseUrl) || $baseUrl === '' || !is_string($release) || $release === '' || !is_string($output) || $output === '') {
    fwrite(STDERR, "Usage: php HostBenchmark.php --base-url=URL --release=NAME --output=FILE\n");
    exit(2);
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, "The curl extension is required for host benchmarking.\n");
    exit(2);
}

/**
 * @param list<float> $values
 */
function uidPercentile(array $values, float $percentile): float
{
    if ($values === []) {
        return 0.0;
    }

    sort($values, SORT_NUMERIC);
    $index = max(0, (int) ceil(count($values) * $percentile) - 1);

    return $values[$index];
}

/**
 * @param list<float> $values
 */
function uidAverage(array $values): float
{
    return $values === [] ? 0.0 : array_sum($values) / count($values);
}

/**
 * @return array{
 *   attempted:int,successful:int,failed:int,timeouts:int,rpm:float,
 *   latencies:list<float>,duplicates:int
 * }
 */
function uidRunLoad(string $url, int $concurrency, int $operations, int $idsPerResponse): array
{
    $multi = curl_multi_init();
    $launched = 0;
    $active = 0;
    $successful = 0;
    $failed = 0;
    $timeouts = 0;
    $latencies = [];
    $seen = [];
    $duplicates = 0;

    $launch = static function () use (
        $multi,
        $url,
        &$launched,
        &$active,
        $operations,
    ): void {
        if ($launched >= $operations) {
            return;
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 2_000,
            CURLOPT_TIMEOUT_MS => 5_000,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        curl_multi_add_handle($multi, $handle);
        ++$launched;
        ++$active;
    };

    for ($index = 0; $index < min($concurrency, $operations); ++$index) {
        $launch();
    }

    $started = hrtime(true);

    while ($active > 0) {
        do {
            $status = curl_multi_exec($multi, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        if ($status !== CURLM_OK) {
            ++$failed;

            break;
        }

        while (($info = curl_multi_info_read($multi)) !== false) {
            $handle = $info['handle'];
            $body = curl_multi_getcontent($handle);
            $httpCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $latency = (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1_000;

            $valid = $info['result'] === CURLE_OK && $httpCode === 200 && is_string($body);
            $decoded = $valid ? json_decode($body, true) : null;
            $ids = is_array($decoded) ? ($decoded['ids'] ?? null) : null;

            if (!is_array($ids) || count($ids) !== $idsPerResponse || array_filter($ids, is_string(...)) !== $ids) {
                $valid = false;
            }

            if ($valid) {
                ++$successful;
                $latencies[] = $latency;

                foreach ($ids as $id) {
                    if (isset($seen[$id])) {
                        ++$duplicates;
                    } else {
                        $seen[$id] = true;
                    }
                }
            } else {
                ++$failed;
                if ($info['result'] === CURLE_OPERATION_TIMEDOUT) {
                    ++$timeouts;
                }
            }

            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
            --$active;

            if ($launched < $operations) {
                $launch();
            }
        }

        if ($running > 0) {
            $selected = curl_multi_select($multi, 0.5);
            if ($selected === -1) {
                usleep(1_000);
            }
        }
    }

    curl_multi_close($multi);
    $seconds = max((hrtime(true) - $started) / 1_000_000_000, 0.000001);

    return [
        'attempted' => $operations,
        'successful' => $successful,
        'failed' => $failed + max(0, $operations - $successful - $failed),
        'timeouts' => $timeouts,
        'rpm' => ($successful / $seconds) * 60,
        'latencies' => $latencies,
        'duplicates' => $duplicates,
    ];
}

/**
 * @return array<string, mixed>
 */
function uidEnvironment(string $release): array
{
    $cpuModel = 'unknown';
    $cpuInfo = is_readable('/proc/cpuinfo') ? file_get_contents('/proc/cpuinfo') : false;
    if (is_string($cpuInfo) && preg_match('/^model name\s*:\s*(.+)$/m', $cpuInfo, $matches) === 1) {
        $cpuModel = trim($matches[1]);
    }

    $extensions = get_loaded_extensions();
    sort($extensions, SORT_STRING);

    $environment = [
        'stable' => true,
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
        'operating_system' => PHP_OS_FAMILY . ' ' . php_uname('r'),
        'cpu_model' => $cpuModel,
        'memory_limit' => (string) ini_get('memory_limit'),
        'opcache' => (string) ini_get('opcache.enable_cli'),
        'jit' => (string) ini_get('opcache.jit'),
        'xdebug' => extension_loaded('xdebug'),
        'extensions' => $extensions,
        'runner' => (string) (getenv('RUNNER_NAME') ?: 'github-actions'),
    ];
    $fingerprintSource = $environment;
    $environment['fingerprint'] = hash(
        'sha256',
        json_encode($fingerprintSource, JSON_THROW_ON_ERROR),
    );
    $environment['release'] = $release;

    return $environment;
}

$workloadDefinitions = [
    [
        'route' => 'cuid2-one',
        'ids_per_response' => 1,
        'operations' => 1_000,
    ],
    [
        'route' => 'cuid2-batch',
        'ids_per_response' => 100,
        'operations' => 150,
    ],
    [
        'route' => 'snowflake-contended',
        'ids_per_response' => 1,
        'operations' => 800,
    ],
];
$concurrencies = [1, 5, 20, 50];
$repetitions = 5;
$warmupOperations = 40;
$workloads = [];
$overallFailure = false;

foreach ($workloadDefinitions as $definition) {
    foreach ($concurrencies as $concurrency) {
        $url = rtrim($baseUrl, '/') . '/' . $definition['route'];
        uidRunLoad(
            $url,
            $concurrency,
            $warmupOperations,
            $definition['ids_per_response'],
        );

        $rpms = [];
        $latencies = [];
        $attempted = 0;
        $successful = 0;
        $failed = 0;
        $timeouts = 0;
        $duplicates = 0;

        for ($repetition = 0; $repetition < $repetitions; ++$repetition) {
            $result = uidRunLoad(
                $url,
                $concurrency,
                $definition['operations'],
                $definition['ids_per_response'],
            );
            $rpms[] = $result['rpm'];
            $latencies = [...$latencies, ...$result['latencies']];
            $attempted += $result['attempted'];
            $successful += $result['successful'];
            $failed += $result['failed'];
            $timeouts += $result['timeouts'];
            $duplicates += $result['duplicates'];
        }

        sort($rpms, SORT_NUMERIC);
        $medianRpm = uidPercentile($rpms, 0.50);
        $spread = $medianRpm > 0
            ? ((uidPercentile($rpms, 0.75) - uidPercentile($rpms, 0.25)) / $medianRpm) * 100
            : 100.0;
        $stable = $spread <= 15.0 && $failed === 0 && $duplicates === 0;

        if (!$stable) {
            $overallFailure = true;
        }

        $workloads[] = [
            'name' => $definition['route'] . '-c' . $concurrency,
            'type' => 'http',
            'metadata' => [
                'route' => '/' . $definition['route'],
                'operations_per_repetition' => $definition['operations'],
                'ids_per_response' => $definition['ids_per_response'],
                'duplicate_ids' => $duplicates,
            ],
            'repetitions' => $repetitions,
            'warmup_operations' => $warmupOperations,
            'duration_seconds' => 0,
            'concurrency' => $concurrency,
            'result' => [
                'attempted_operations' => $attempted,
                'successful_operations' => $successful,
                'failed_operations' => $failed,
                'timeouts' => $timeouts,
                'successful_rpm' => round($medianRpm, 5),
                'error_rate' => $attempted === 0 ? 0.0 : $failed / $attempted,
                'latency_ms' => [
                    'minimum' => $latencies === [] ? null : round(min($latencies), 5),
                    'average' => $latencies === [] ? null : round(uidAverage($latencies), 5),
                    'p50' => $latencies === [] ? null : round(uidPercentile($latencies, 0.50), 5),
                    'p95' => $latencies === [] ? null : round(uidPercentile($latencies, 0.95), 5),
                    'p99' => $latencies === [] ? null : round(uidPercentile($latencies, 0.99), 5),
                    'maximum' => $latencies === [] ? null : round(max($latencies), 5),
                ],
                'cpu' => [
                    'average_percent' => null,
                    'peak_percent' => null,
                ],
                'memory' => [
                    'average_mb' => null,
                    'peak_mb' => null,
                    'growth_mb' => null,
                ],
                'stability' => [
                    'status' => $stable ? 'stable' : 'unstable',
                    'spread_percent' => round($spread, 5),
                ],
            ],
        ];
    }
}

$document = [
    'schema_version' => 1,
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'environment' => uidEnvironment($release),
    'workloads' => $workloads,
];

file_put_contents(
    $output,
    json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
);

exit($overallFailure ? 1 : 0);
