<?php

declare(strict_types=1);

$options = getopt('', [
    'base-url:',
    'release:',
    'output:',
    'route:',
    'concurrency:',
    'duration:',
    'repetitions:',
    'ids-per-response:',
    'warmup:',
]);

$baseUrl = $options['base-url'] ?? null;
$release = $options['release'] ?? null;
$output = $options['output'] ?? null;
$route = $options['route'] ?? null;
$concurrency = filter_var($options['concurrency'] ?? null, FILTER_VALIDATE_INT);
$duration = filter_var($options['duration'] ?? null, FILTER_VALIDATE_INT);
$repetitions = filter_var($options['repetitions'] ?? null, FILTER_VALIDATE_INT);
$idsPerResponse = filter_var($options['ids-per-response'] ?? null, FILTER_VALIDATE_INT);
$warmup = filter_var($options['warmup'] ?? null, FILTER_VALIDATE_INT);

if (
    !is_string($baseUrl)
    || $baseUrl === ''
    || !is_string($release)
    || $release === ''
    || !is_string($output)
    || $output === ''
    || !is_string($route)
    || $route === ''
    || !is_int($concurrency)
    || $concurrency < 1
    || !is_int($duration)
    || $duration < 1
    || !is_int($repetitions)
    || $repetitions < 3
    || !is_int($idsPerResponse)
    || $idsPerResponse < 1
    || !is_int($warmup)
    || $warmup < 1
) {
    throw new InvalidArgumentException('Invalid fixed-duration host benchmark configuration');
}

if (!extension_loaded('curl')) {
    throw new RuntimeException('The curl extension is required for host benchmarking');
}

const UID_MAX_LATENCY_SAMPLES = 200_000;

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

function uidCreateHandle(string $url): CurlHandle
{
    $handle = curl_init($url);
    $handle instanceof CurlHandle || throw new RuntimeException('Unable to create benchmark request handle');

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => 2_000,
        CURLOPT_TIMEOUT_MS => 5_000,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    return $handle;
}

/**
 * @param array{result:int,handle:CurlHandle} $info
 * @return array{successful:bool,timeout:bool,latency:float,duplicates:int}
 */
function uidInspectCompletion(array $info, int $idsPerResponse): array
{
    $handle = $info['handle'];
    $body = curl_multi_getcontent($handle);
    $httpCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $latency = (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1_000;
    $successful = $info['result'] === CURLE_OK && $httpCode === 200 && is_string($body);
    $decoded = $successful ? json_decode($body, true) : null;
    $ids = is_array($decoded) ? ($decoded['ids'] ?? null) : null;

    if (!is_array($ids) || count($ids) !== $idsPerResponse) {
        $successful = false;
        $ids = [];
    }

    $duplicates = 0;
    $responseIds = [];

    foreach ($ids as $id) {
        if (!is_string($id) || $id === '') {
            $successful = false;

            continue;
        }

        if (isset($responseIds[$id])) {
            ++$duplicates;
        } else {
            $responseIds[$id] = true;
        }
    }

    return [
        'successful' => $successful,
        'timeout' => $info['result'] === CURLE_OPERATION_TIMEDOUT,
        'latency' => $latency,
        'duplicates' => $duplicates,
    ];
}

/**
 * @return array{attempted:int,successful:int,failed:int,timeouts:int,duplicates:int}
 */
function uidRunOperations(
    string $url,
    int $concurrency,
    int $operations,
    int $idsPerResponse,
): array {
    $multi = curl_multi_init();
    $launched = 0;
    $active = 0;
    $attempted = 0;
    $successful = 0;
    $failed = 0;
    $timeouts = 0;
    $duplicates = 0;

    $launch = static function () use ($multi, $url, $operations, &$launched, &$active): void {
        if ($launched >= $operations) {
            return;
        }

        curl_multi_add_handle($multi, uidCreateHandle($url));
        ++$launched;
        ++$active;
    };

    for ($index = 0; $index < min($concurrency, $operations); ++$index) {
        $launch();
    }

    while ($active > 0) {
        do {
            $status = curl_multi_exec($multi, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        $status === CURLM_OK || throw new RuntimeException('Host benchmark warmup execution failed');

        while (($info = curl_multi_info_read($multi)) !== false) {
            $result = uidInspectCompletion($info, $idsPerResponse);
            ++$attempted;

            if ($result['successful']) {
                ++$successful;
            } else {
                ++$failed;
            }

            if ($result['timeout']) {
                ++$timeouts;
            }

            $duplicates += $result['duplicates'];
            curl_multi_remove_handle($multi, $info['handle']);
            --$active;
            $launch();
        }

        if ($running > 0) {
            $selected = curl_multi_select($multi, 0.5);
            if ($selected === -1) {
                usleep(1_000);
            }
        }
    }

    unset($multi);

    return [
        'attempted' => $attempted,
        'successful' => $successful,
        'failed' => $failed,
        'timeouts' => $timeouts,
        'duplicates' => $duplicates,
    ];
}

/**
 * @return array{
 *   attempted:int,
 *   successful:int,
 *   failed:int,
 *   timeouts:int,
 *   rpm:float,
 *   latencies:list<float>,
 *   duplicates:int,
 *   elapsed_seconds:float
 * }
 */
function uidRunDuration(
    string $url,
    int $concurrency,
    int $durationSeconds,
    int $idsPerResponse,
): array {
    $multi = curl_multi_init();
    $active = 0;
    $attempted = 0;
    $successful = 0;
    $failed = 0;
    $timeouts = 0;
    $duplicates = 0;
    $latencies = [];
    $started = hrtime(true);
    $stopAt = $started + ($durationSeconds * 1_000_000_000);

    for ($index = 0; $index < $concurrency; ++$index) {
        curl_multi_add_handle($multi, uidCreateHandle($url));
        ++$active;
    }

    while ($active > 0) {
        do {
            $status = curl_multi_exec($multi, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        $status === CURLM_OK || throw new RuntimeException('Host benchmark curl multi execution failed');

        while (($info = curl_multi_info_read($multi)) !== false) {
            $result = uidInspectCompletion($info, $idsPerResponse);
            ++$attempted;

            if ($result['successful']) {
                ++$successful;
                if (count($latencies) < UID_MAX_LATENCY_SAMPLES) {
                    $latencies[] = $result['latency'];
                }
            } else {
                ++$failed;
            }

            if ($result['timeout']) {
                ++$timeouts;
            }

            $duplicates += $result['duplicates'];
            curl_multi_remove_handle($multi, $info['handle']);
            --$active;

            if (hrtime(true) < $stopAt) {
                curl_multi_add_handle($multi, uidCreateHandle($url));
                ++$active;
            }
        }

        if ($running > 0) {
            $selected = curl_multi_select($multi, 0.5);
            if ($selected === -1) {
                usleep(1_000);
            }
        }
    }

    unset($multi);

    $elapsed = max((hrtime(true) - $started) / 1_000_000_000, 0.000001);

    return [
        'attempted' => $attempted,
        'successful' => $successful,
        'failed' => $failed,
        'timeouts' => $timeouts,
        'rpm' => ($successful / $elapsed) * 60,
        'latencies' => $latencies,
        'duplicates' => $duplicates,
        'elapsed_seconds' => $elapsed,
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

$url = rtrim($baseUrl, '/') . '/' . ltrim($route, '/');
$warmupResult = uidRunOperations($url, $concurrency, $warmup, $idsPerResponse);

if (
    $warmupResult['failed'] !== 0
    || $warmupResult['timeouts'] !== 0
    || $warmupResult['duplicates'] !== 0
) {
    throw new RuntimeException('Host benchmark warmup produced invalid responses');
}

$rpms = [];
$latencies = [];
$attempted = 0;
$successful = 0;
$failed = 0;
$timeouts = 0;
$duplicates = 0;
$elapsedSeconds = 0.0;

for ($repetition = 0; $repetition < $repetitions; ++$repetition) {
    $result = uidRunDuration($url, $concurrency, $duration, $idsPerResponse);
    $rpms[] = $result['rpm'];
    $attempted += $result['attempted'];
    $successful += $result['successful'];
    $failed += $result['failed'];
    $timeouts += $result['timeouts'];
    $duplicates += $result['duplicates'];
    $elapsedSeconds += $result['elapsed_seconds'];

    $remaining = UID_MAX_LATENCY_SAMPLES - count($latencies);
    if ($remaining > 0) {
        $latencies = [...$latencies, ...array_slice($result['latencies'], 0, $remaining)];
    }
}

sort($rpms, SORT_NUMERIC);
$medianRpm = uidPercentile($rpms, 0.50);
$spread = $medianRpm > 0
    ? ((uidPercentile($rpms, 0.75) - uidPercentile($rpms, 0.25)) / $medianRpm) * 100
    : 100.0;
$stable = $spread <= 15.0 && $failed === 0 && $duplicates === 0 && $timeouts === 0;
$name = $route . '-c' . $concurrency;

$document = [
    'schema_version' => 1,
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'environment' => uidEnvironment($release),
    'workloads' => [[
        'name' => $name,
        'type' => 'http',
        'metadata' => [
            'route' => '/' . ltrim($route, '/'),
            'trial_duration_seconds' => $duration,
            'ids_per_response' => $idsPerResponse,
        ],
        'repetitions' => $repetitions,
        'warmup_operations' => $warmup,
        'duration_seconds' => $duration * $repetitions,
        'concurrency' => $concurrency,
        'result' => [
            'attempted_operations' => $attempted,
            'successful_operations' => $successful,
            'failed_operations' => $failed,
            'timeouts' => $timeouts,
            'duplicate_ids' => $duplicates,
            'successful_rpm' => round($medianRpm, 5),
            'error_rate' => $attempted === 0 ? 0.0 : $failed / $attempted,
            'measured_elapsed_seconds' => round($elapsedSeconds, 5),
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
    ]],
];

file_put_contents(
    $output,
    json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
);

if (!$stable) {
    throw new RuntimeException('Host benchmark did not reach a stable valid state');
}
